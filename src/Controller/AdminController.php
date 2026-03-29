<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Service\ReservationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private ReservationMailer $reservationMailer,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
    ) {}

    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(EventRepository $eventRepo, ReservationRepository $reservationRepo): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'totalEvents'        => $eventRepo->countAll(),
            'totalReservations'  => count($reservationRepo->findAll()),
            'recentReservations' => $reservationRepo->findRecentWithEvent(5),
            'upcomingEvents'     => $eventRepo->findUpcoming(),
        ]);
    }

    #[Route('/debug/email-test', name: 'app_admin_debug_email', methods: ['GET'])]
    public function debugEmailTest(Request $request): Response
    {
        $to = $request->query->get('to', $this->getUser()?->getUserIdentifier() . '@example.com');

        try {
            $email = (new Email())
                ->from(new Address('noreply@eventhub.com', 'EventHub'))
                ->to($to)
                ->subject('EventHub — Admin Email Debug Test ' . date('H:i:s'))
                ->html('<h2>EventHub Debug Email</h2><p>Sent at: ' . date('Y-m-d H:i:s') . '</p><p>If you see this, SMTP works!</p>');

            $this->mailer->send($email);

            $this->addFlash('success', '✅ Email sent to ' . $to . ' — check your inbox and var/log/mailer.log');
        } catch (\Throwable $e) {
            $this->addFlash('error', '❌ Email FAILED: ' . $e->getMessage() . ' — check var/log/mailer.log');
            $this->logger->error('[AdminDebug] Email test failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    // ─── Events CRUD ────────────────────────────────────────────────────────────

    #[Route('/events', name: 'app_admin_events')]
    public function events(EventRepository $eventRepo): Response
    {
        return $this->render('admin/events/index.html.twig', [
            'events' => $eventRepo->findBy([], ['date' => 'ASC']),
        ]);
    }

    #[Route('/events/new', name: 'app_admin_event_new', methods: ['GET', 'POST'])]
    public function newEvent(Request $request): Response
    {
        $event = new Event();
        $form  = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $event);
            $this->em->persist($event);
            $this->em->flush();
            $this->addFlash('success', 'Event "' . $event->getTitle() . '" created successfully.');
            return $this->redirectToRoute('app_admin_events');
        }

        return $this->render('admin/events/form.html.twig', [
            'form'  => $form,
            'event' => $event,
            'title' => 'Create New Event',
        ]);
    }

    #[Route('/events/{id}/edit', name: 'app_admin_event_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editEvent(int $id, Request $request, EventRepository $eventRepo): Response
    {
        $event = $eventRepo->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form, $event);
            $this->em->flush();
            $this->addFlash('success', 'Event "' . $event->getTitle() . '" updated successfully.');
            return $this->redirectToRoute('app_admin_events');
        }

        return $this->render('admin/events/form.html.twig', [
            'form'  => $form,
            'event' => $event,
            'title' => 'Edit Event',
        ]);
    }

    #[Route('/events/{id}/delete', name: 'app_admin_event_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteEvent(int $id, Request $request, EventRepository $eventRepo): Response
    {
        $event = $eventRepo->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            $title = $event->getTitle();
            $this->em->remove($event);
            $this->em->flush();
            $this->addFlash('success', 'Event "' . $title . '" deleted.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_admin_events');
    }

    // ─── Reservations ───────────────────────────────────────────────────────────

    #[Route('/reservations', name: 'app_admin_reservations')]
    public function reservations(Request $request, ReservationRepository $reservationRepo, EventRepository $eventRepo): Response
    {
        $eventId = $request->query->getInt('event', 0);
        $reservations = $eventId
            ? $reservationRepo->findByEvent($eventId)
            : $reservationRepo->findRecentWithEvent(500);

        return $this->render('admin/reservations/index.html.twig', [
            'reservations' => $reservations,
            'events'       => $eventRepo->findBy([], ['title' => 'ASC']),
            'selectedEvent' => $eventId,
        ]);
    }

    #[Route('/reservations/{id}/resend-email', name: 'app_admin_reservation_resend_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendEmail(int $id, Request $request, ReservationRepository $reservationRepo): Response
    {
        $reservation = $reservationRepo->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Reservation not found.');
        }

        if (!$this->isCsrfTokenValid('resend_email_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_reservations');
        }

        try {
            $this->reservationMailer->sendConfirmation($reservation);
            $this->addFlash('success', sprintf(
                'Confirmation email resent to %s (%s).',
                $reservation->getName(),
                $reservation->getEmail()
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to resend confirmation email: ' . $e->getMessage());
            $this->addFlash('error', 'Failed to send email: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_reservations');
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function handleImageUpload(mixed $form, Event $event): void
    {
        $imageFile = $form->get('imageFile')->getData();
        if (!$imageFile) {
            return;
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $this->slugger->slug($originalFilename);
        $newFilename      = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Remove old image file
        if ($event->getImage()) {
            $oldFile = $uploadDir . '/' . $event->getImage();
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $imageFile->move($uploadDir, $newFilename);
        $event->setImage($newFilename);
    }
}
