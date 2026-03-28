<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
