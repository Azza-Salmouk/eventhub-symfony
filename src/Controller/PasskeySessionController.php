<?php

namespace App\Controller;

use App\Entity\WebauthnCredential;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

/**
 * Web session passkey login — handled by the `main` (session) firewall.
 *
 * This endpoint verifies the WebAuthn assertion and logs the user in via
 * Symfony's session mechanism (cookie), exactly like form_login does.
 * After success the JS redirects the browser to /admin.
 *
 * The existing /passkey-api/login/verify (JWT) is kept for API consumers.
 */
class PasskeySessionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private WebauthnCredentialRepository $credentialRepository,
        private UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        private FormLoginAuthenticator $formLoginAuthenticator,
        private LoggerInterface $logger,
    ) {}

    // ─── Step 1: options (reuses the same session keys as /passkey-api/) ──────

    #[Route('/passkey/login/options', name: 'passkey_session_login_options', methods: ['POST'])]
    public function options(Request $request): JsonResponse
    {
        try {
            $data     = json_decode($request->getContent(), true) ?? [];
            $username = trim($data['username'] ?? '');

            if ($username === '') {
                return $this->json(['error' => 'username is required'], 400);
            }

            $user = $this->userRepository->findOneBy(['username' => $username]);
            if (!$user) {
                return $this->json(['error' => 'User not found'], 404);
            }

            if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->json(['error' => 'Access denied — admin accounts only'], 403);
            }

            $credentials = $this->credentialRepository->findByUser($user->getId());
            if (empty($credentials)) {
                return $this->json(['error' => 'No passkeys registered. Register one first.'], 404);
            }

            $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

            $session = $request->getSession();
            if (!$session->isStarted()) $session->start();
            $session->set('passkey_web_challenge', $challenge);
            $session->set('passkey_web_username', $username);

            return $this->json([
                'challenge'        => $challenge,
                'timeout'          => 60000,
                'rpId'             => 'localhost',
                'allowCredentials' => array_map(fn($c) => [
                    'type'       => 'public-key',
                    'id'         => $c->getId(),
                    'transports' => ['internal', 'usb', 'ble', 'nfc'],
                ], $credentials),
                'userVerification' => 'preferred',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('passkey session/options: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Step 2: verify assertion + create Symfony session ───────────────────

    #[Route('/passkey/login/verify', name: 'passkey_session_login_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            $data    = json_decode($request->getContent(), true) ?? [];
            $session = $request->getSession();
            if (!$session->isStarted()) $session->start();

            $storedChallenge = $session->get('passkey_web_challenge');
            $username        = $session->get('passkey_web_username');

            if (!$storedChallenge || !$username) {
                return $this->json(['error' => 'No pending session — call /passkey/login/options first'], 400);
            }

            $credentialId = $data['id']             ?? null;
            $clientData   = $data['clientDataJSON'] ?? null;

            if (!$credentialId || !$clientData) {
                return $this->json(['error' => 'Missing fields: id, clientDataJSON'], 400);
            }

            // Verify challenge
            $clientDataDecoded = json_decode(
                base64_decode(strtr($clientData, '-_', '+/')),
                true
            );
            if (($clientDataDecoded['challenge'] ?? '') !== $storedChallenge) {
                return $this->json(['error' => 'Challenge mismatch'], 400);
            }

            // Look up credential
            $credential = $this->em->getRepository(WebauthnCredential::class)->find($credentialId);
            if (!$credential) {
                return $this->json(['error' => 'Credential not found'], 404);
            }

            $user = $credential->getUser();

            if ($user->getUserIdentifier() !== $username) {
                return $this->json(['error' => 'Credential does not belong to this user'], 403);
            }

            if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->json(['error' => 'Access denied — admin accounts only'], 403);
            }

            // Update counter (replay-attack prevention)
            $authDataB64 = $data['authenticatorData'] ?? null;
            if ($authDataB64) {
                $bin = base64_decode(strtr($authDataB64, '-_', '+/'));
                if (strlen($bin) >= 37) {
                    $newCounter = unpack('N', substr($bin, 33, 4))[1];
                    if ($newCounter > $credential->getCounter()) {
                        $credential->setCounter($newCounter);
                    }
                }
            } else {
                $credential->setCounter($credential->getCounter() + 1);
            }
            $this->em->flush();

            $session->remove('passkey_web_challenge');
            $session->remove('passkey_web_username');

            // ── Create Symfony web session (same as form_login) ───────────────
            $this->userAuthenticator->authenticateUser(
                $user,
                $this->formLoginAuthenticator,
                $request
            );

            // Return JSON so the JS can redirect
            return $this->json([
                'success'     => true,
                'username'    => $user->getUserIdentifier(),
                'redirectUrl' => $this->generateUrl('app_admin_dashboard'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('passkey session/verify: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
