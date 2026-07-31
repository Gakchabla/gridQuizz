<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Player;
use App\Entity\Question;
use App\Entity\Session;

/** @implements ProcessorInterface<Question, Question> */
final class ResolveQuestionProcessor implements ProcessorInterface
{
    public function __construct(private readonly PersistProcessor $persistProcessor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Question
    {
        $data->setAnswered(true);

        $session = $data->getSession();
        $currentPlayer = $session->getCurrentPlayer();

        if (true === $data->isCorrect() && null !== $currentPlayer) {
            $points = $currentPlayer->getTheme() === $data->getTheme() ? 1 : 2;
            $currentPlayer->setScore($currentPlayer->getScore() + $points);
        }

        $this->advanceTurn($session);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function advanceTurn(Session $session): void
    {
        $players = $session->getPlayers()->toArray();

        if ([] === $players) {
            $session->setCurrentPlayer(null);

            return;
        }

        usort($players, static fn (Player $a, Player $b): int => $a->getId() <=> $b->getId());

        $currentIndex = array_search($session->getCurrentPlayer(), $players, true);
        $nextIndex = false === $currentIndex ? 0 : ($currentIndex + 1) % count($players);

        $session->setCurrentPlayer($players[$nextIndex]);
    }
}
