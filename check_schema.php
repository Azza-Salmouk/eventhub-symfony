<?php
require 'vendor/autoload.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $em->getConnection();
$r1 = $conn->fetchAssociative('SHOW COLUMNS FROM event WHERE Field = ?', ['image']);
$r2 = $conn->fetchAssociative('SHOW COLUMNS FROM messenger_messages WHERE Field = ?', ['delivered_at']);
$r3 = $conn->fetchAssociative('SHOW COLUMNS FROM `user` WHERE Field = ?', ['roles']);
echo "event.image: " . json_encode($r1) . "\n";
echo "msg.delivered_at: " . json_encode($r2) . "\n";
echo "user.roles: " . json_encode($r3) . "\n";
