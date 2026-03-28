<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events', name: 'app_events')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $page   = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('q', ''));
        $location = trim((string) $request->query->get('location', ''));
        $limit  = 6;

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
    public function reserve(int $id, Request $request, EventRepository $eventRepository, EntityManagerInterface $em): Response
    {
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

            $this->addFlash('success', sprintf(
                'Reservation confirmed for "%s"! We look forward to seeing you.',
                $event->getTitle()
            ));
            return $this->redirectToRoute('app_event_show', ['id' => $id]);
        }

        return $this->render('event/reserve.html.twig', [
            'event' => $event,
            'form'  => $form,
        ]);
    }
}
