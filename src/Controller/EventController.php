<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\EventRepository;
use App\Service\ReservationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events', name: 'app_events')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $page     = max(1, $request->query->getInt('page', 1));
        $search   = trim((string) $request->query->get('q', ''));
        $location = trim((string) $request->query->get('location', ''));
        $limit    = 6;

        $paginator = $eventRepository->findPaginatedWithSearch($page, $limit, $search ?: null, $location ?: null);
        $total     = count($paginator);
        $pages     = (int) ceil($total / $limit);

        return $this->render('event/index.html.twig', [
            'events'      => $paginator,
            'total'       => $total,
            'currentPage' => $page,
            'totalPages'  => $pages,
            'search'      => $search,
            'location'    => $location,
        ]);
    }

    #[Route('/events/{id}', name: 'app_event_show', requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepository $eventRepository): Response
    {
        $event = $eventRepository->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        return $this->render('event/show.html.twig', ['event' => $event]);
    }

    #[Route('/events/{id}/reserve', name: 'app_reserve', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reserve(
        int $id,
        Request $request,
        EventRepository $eventRepository,
        EntityManagerInterface $em,
        ReservationMailer $mailer,
        LoggerInterface $logger,
    ): Response {
        $event = $eventRepository->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        if ($event->getAvailableSeats() <= 0) {
            $this->addFlash('warning', 'Sorry, this event is fully booked.');
            return $this->redirectToRoute('app_event_show', ['id' => $id]);
        }

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setEvent($event);
            $reservation->setCreatedAt(new \DateTimeImmutable());
            $em->persist($reservation);
            $em->flush();

            // Send confirmation email — failure is non-fatal
            try {
                $logger->info('[EventController] Sending reservation confirmation email', [
                    'reservation_id' => $reservation->getId(),
                    'to'             => $reservation->getEmail(),
                    'event'          => $event->getTitle(),
                ]);
                $mailer->sendConfirmation($reservation);
                $logger->info('[EventController] Confirmation email dispatched OK', [
                    'reservation_id' => $reservation->getId(),
                ]);
            } catch (\Throwable $e) {
                $logger->error('[EventController] Failed to send confirmation email: ' . $e->getMessage(), [
                    'reservation_id' => $reservation->getId(),
                    'email'          => $reservation->getEmail(),
                    'exception'      => $e,
                ]);
            }

            // In dev: warn visibly if emails are being discarded
            if ($mailer->isNullTransport() && $this->getParameter('kernel.environment') === 'dev') {
                $this->addFlash('warning',
                    '⚠️ Dev mode: MAILER_DSN=null://null — confirmation email was NOT sent. ' .
                    'Set a real DSN in .env.local to enable email delivery.'
                );
            }

            $this->addFlash('success', sprintf(
                'Reservation confirmed for "%s"! A confirmation email has been sent to %s.',
                $event->getTitle(),
                $reservation->getEmail()
            ));
            return $this->redirectToRoute('app_event_show', ['id' => $id]);
        }

        return $this->render('event/reserve.html.twig', [
            'event' => $event,
            'form'  => $form,
        ]);
    }
}
