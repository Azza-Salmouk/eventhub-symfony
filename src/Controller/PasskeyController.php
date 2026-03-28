<?php

namespace App\Controller;

use App\Entity\WebauthnCredential;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Passkey / WebAuthn controller.
 *
 * Routes are under /passkey-api/ (NOT /api/) so they are handled by the
 * session-based `main` firewall instead of the stateless JWT `api` firewall.
 * This ensures:
 *   - The session cookie is shared with the /admin/login page
 *   - LexikJWT never intercepts these routes
 *   - Responses are always JSON (never HTML redirects)
 *
 * All endpoints wrap logic in try/catch and always return JSON.
 */
#[Route('/passkey-api')]
class PasskeyController extends AbstractController
{
    private const RP_ID   = 'localhost';
    private const RP_NAME = 'EventHub';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private WebauthnCredentialRepository $credentialRepository,
        private JWTTokenManagerInterface $jwtManager,
        private LoggerInterface $logger,
    ) {}

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64decode(string $b64): string
    {
        return base64_decode(strtr($b64, '-_', '+/'));
    }

    private function session(Request $request): SessionInterface
    {
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        return $session;
    }

    // ─── Registration ─────────────────────────────────────────────────────────

    #[Route('/register/options', name: 'api_passkey_register_options', methods: ['POST'])]
    public function registerOptions(Request $request): JsonResponse
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

            $challenge = $this->base64url(random_bytes(32));
            $session   = $this->session($request);
            $session->set('passkey_register_challenge', $challenge);
            $session->set('passkey_register_username', $username);

            return $this->json([
                'rp' => ['id' => self::RP_ID, 'name' => self::RP_NAME],
                'user' => [
                    'id'          => $this->base64url((string) $user->getId()),
                    'name'        => $user->getUserIdentifier(),
                    'displayName' => $user->getUserIdentifier(),
                ],
                'challenge'        => $challenge,
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],   // ES256
                    ['type' => 'public-key', 'alg' => -257],  // RS256
                ],
                'timeout'                => 60000,
                'attestation'            => 'none',
                'authenticatorSelection' => [
                    'residentKey'      => 'preferred',
                    'userVerification' => 'preferred',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('passkey register/options error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/register/verify', name: 'api_passkey_register_verify', methods: ['POST'])]
    public function registerVerify(Request $request): JsonResponse
    {
        try {
            $data    = json_decode($request->getContent(), true) ?? [];
            $session = $this->session($request);

            $storedChallenge = $session->get('passkey_register_challenge');
            $username        = $session->get('passkey_register_username');

            if (!$storedChallenge || !$username) {
                return $this->json(['error' => 'No pending registration session — call /register/options first'], 400);
            }

            $credentialId = $data['id']                ?? null;
            $clientData   = $data['clientDataJSON']    ?? null;
            $attestation  = $data['attestationObject'] ?? null;

            if (!$credentialId || !$clientData || !$attestation) {
                return $this->json(['error' => 'Missing fields: id, clientDataJSON, attestationObject'], 400);
            }

            $clientDataDecoded = json_decode($this->b64decode($clientData), true);
            if (($clientDataDecoded['challenge'] ?? '') !== $storedChallenge) {
                return $this->json(['error' => 'Challenge mismatch'], 400);
            }

            $user = $this->userRepository->findOneBy(['username' => $username]);
            if (!$user) {
                return $this->json(['error' => 'User not found'], 404);
            }

            if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->json(['error' => 'Access denied — admin accounts only'], 403);
            }

            if ($this->em->getRepository(WebauthnCredential::class)->find($credentialId)) {
                return $this->json(['error' => 'Credential already registered'], 409);
            }

            $credential = new WebauthnCredential($credentialId, $user, $attestation);
            $this->em->persist($credential);
            $this->em->flush();

            $session->remove('passkey_register_challenge');
            $session->remove('passkey_register_username');

            return $this->json(['status' => 'ok', 'message' => 'Passkey registered successfully']);
        } catch (\Throwable $e) {
            $this->logger->error('passkey register/verify error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    #[Route('/login/options', name: 'api_passkey_login_options', methods: ['POST'])]
    public function loginOptions(Request $request): JsonResponse
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
                return $this->json(['error' => 'No passkeys registered for this user. Register one first.'], 404);
            }

            $challenge = $this->base64url(random_bytes(32));
            $session   = $this->session($request);
            $session->set('passkey_login_challenge', $challenge);
            $session->set('passkey_login_username', $username);

            return $this->json([
                'challenge'        => $challenge,
                'timeout'          => 60000,
                'rpId'             => self::RP_ID,
                'allowCredentials' => array_map(fn($c) => [
                    'type'       => 'public-key',
                    'id'         => $c->getId(),
                    'transports' => ['internal', 'usb', 'ble', 'nfc'],
                ], $credentials),
                'userVerification' => 'preferred',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('passkey login/options error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/login/verify', name: 'api_passkey_login_verify', methods: ['POST'])]
    public function loginVerify(Request $request): JsonResponse
    {
        try {
            $data    = json_decode($request->getContent(), true) ?? [];
            $session = $this->session($request);

            $storedChallenge = $session->get('passkey_login_challenge');
            $username        = $session->get('passkey_login_username');

            if (!$storedChallenge || !$username) {
                return $this->json(['error' => 'No pending authentication session — call /login/options first'], 400);
            }

            $credentialId = $data['id']             ?? null;
            $clientData   = $data['clientDataJSON'] ?? null;

            if (!$credentialId || !$clientData) {
                return $this->json(['error' => 'Missing fields: id, clientDataJSON'], 400);
            }

            $clientDataDecoded = json_decode($this->b64decode($clientData), true);
            if (($clientDataDecoded['challenge'] ?? '') !== $storedChallenge) {
                return $this->json(['error' => 'Challenge mismatch'], 400);
            }

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
                $bin = $this->b64decode($authDataB64);
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

            $session->remove('passkey_login_challenge');
            $session->remove('passkey_login_username');

            return $this->json([
                'token'      => $this->jwtManager->create($user),
                'username'   => $user->getUserIdentifier(),
                'roles'      => $user->getRoles(),
                'expires_in' => 3600,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('passkey login/verify error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }
}
