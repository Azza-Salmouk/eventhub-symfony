<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/events', name: 'api_events', methods: ['GET'])]
    public function events(Request $request, EventRepository $eventRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $search = $request->query->get('search');
        $location = $request->query->get('location');

        $paginator = $eventRepository->findPaginatedWithSearch($page, $limit, $search, $location);

        $data = [];
        foreach ($paginator as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'date' => $event->getDate()?->format('Y-m-d H:i:s'),
                'location' => $event->getLocation(),
                'seats' => $event->getSeats(),
                'availableSeats' => $event->getAvailableSeats(),
                'image' => $event->getImage(),
            ];
        }

        return $this->json([
            'data' => $data,
            'total' => count($paginator),
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/events/{id}', name: 'api_event_show', methods: ['GET'])]
    public function eventShow(int $id, EventRepository $eventRepository): JsonResponse
    {
        $event = $eventRepository->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()?->format('Y-m-d H:i:s'),
            'location' => $event->getLocation(),
            'seats' => $event->getSeats(),
            'availableSeats' => $event->getAvailableSeats(),
            'image' => $event->getImage(),
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/admin/stats', name: 'api_admin_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function stats(EventRepository $eventRepo, ReservationRepository $reservationRepo): JsonResponse
    {
        return $this->json([
            'totalEvents' => count($eventRepo->findAll()),
            'totalReservations' => count($reservationRepo->findAll()),
        ]);
    }
}
