<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Player;
use App\Entity\Session;

/** @implements ProcessorInterface<Session, Session> */
final class ResetSessionProcessor implements ProcessorInterface
{
    public function __construct(private readonly PersistProcessor $persistProcessor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        $numbers = range(1, $data->getQuestions()->count());
        shuffle($numbers);

        foreach ($data->getQuestions() as $question) {
            $question->setAnswered(false);
            $question->setNumber(array_pop($numbers));
        }

        $players = $data->getPlayers()->toArray();
        usort($players, static fn (Player $a, Player $b): int => $a->getId() <=> $b->getId());

        foreach ($players as $player) {
            $player->setScore(0);
        }
        $data->setCurrentPlayer($players[0] ?? null);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
