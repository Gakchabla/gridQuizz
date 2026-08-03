<?php

namespace App\Controller;

use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

// Bundles session + themes + questions + players into a single response.
// The front end used to fetch each of these as separate collection requests;
// every request pays a fixed latency floor through Docker Desktop's Windows
// host port-forwarding, so cutting round-trips (not just payload) matters.
class SessionStateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/api/sessions/{id}/state', name: 'session_state', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $session = $this->entityManager->find(Session::class, $id);
        if (!$session) {
            throw $this->createNotFoundException();
        }

        $normalize = fn (mixed $data): mixed => json_decode(
            $this->serializer->serialize($data, 'jsonld'),
            true,
        );

        return new JsonResponse([
            'session' => $normalize($session),
            'themes' => $normalize($session->getThemes()->toArray()),
            'questions' => $normalize($session->getQuestions()->toArray()),
            'players' => $normalize($session->getPlayers()->toArray()),
        ]);
    }
}
