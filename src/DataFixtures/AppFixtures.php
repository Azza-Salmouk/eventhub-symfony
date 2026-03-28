<?php

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // Admin user
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Regular user
        $user = new User();
        $user->setUsername('user');
        $user->setRoles([]);
        $user->setPassword($this->hasher->hashPassword($user, 'user123'));
        $manager->persist($user);

        // Events
        $eventsData = [
            ['Tech Summit 2026', 'A premier technology conference featuring the latest in AI, cloud computing, and software development.', '+30 days', 'San Francisco, CA', 200],
            ['Music Festival', 'Three days of live music across multiple stages featuring top artists from around the world.', '+45 days', 'Austin, TX', 5000],
            ['Startup Pitch Night', 'Watch innovative startups pitch their ideas to top investors. Networking opportunities included.', '+15 days', 'New York, NY', 150],
            ['Photography Workshop', 'Learn professional photography techniques from award-winning photographers. All skill levels welcome.', '+20 days', 'Los Angeles, CA', 30],
            ['Food & Wine Expo', 'Explore culinary delights from top chefs and sommeliers. Tastings, demos, and masterclasses.', '+60 days', 'Chicago, IL', 800],
            ['Marathon 2026', 'Annual city marathon open to all fitness levels. 5K, 10K, half and full marathon options available.', '+90 days', 'Boston, MA', 10000],
            ['Art Exhibition Opening', 'Opening night of the annual contemporary art exhibition featuring works from 50+ local artists.', '+10 days', 'Seattle, WA', 300],
            ['Business Leadership Forum', 'Two-day forum with keynotes and workshops on leadership, strategy, and innovation.', '+35 days', 'Miami, FL', 400],
        ];

        $events = [];
        foreach ($eventsData as [$title, $desc, $dateOffset, $location, $seats]) {
            $event = new Event();
            $event->setTitle($title);
            $event->setDescription($desc);
            $event->setDate(new \DateTime($dateOffset));
            $event->setLocation($location);
            $event->setSeats($seats);
            $manager->persist($event);
            $events[] = $event;
        }

        $manager->flush();

        // Sample reservations
        $names = ['Alice Johnson', 'Bob Smith', 'Carol White', 'David Brown', 'Emma Davis'];
        foreach ($events as $i => $event) {
            for ($j = 0; $j < min(3, $i + 1); $j++) {
                $reservation = new Reservation();
                $reservation->setName($names[$j % count($names)]);
                $reservation->setEmail(strtolower(str_replace(' ', '.', $names[$j % count($names)])) . '@example.com');
                $reservation->setPhone('+1 555 ' . rand(1000, 9999));
                $reservation->setCreatedAt(new \DateTimeImmutable('-' . rand(1, 10) . ' days'));
                $reservation->setEvent($event);
                $manager->persist($reservation);
            }
        }

        $manager->flush();
    }
}
