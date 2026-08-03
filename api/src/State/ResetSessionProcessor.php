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
        // Hardcore questions are a reserve, never placed on the grid at reset
        // time — they only appear via SelectQuestionProcessor's swap.
        $gridQuestions = [];
        foreach ($data->getQuestions() as $question) {
            $question->setAnswered(false);
            if ($question->getTheme()->isHardcore()) {
                $question->setNumber(null);
            } else {
                $gridQuestions[] = $question;
            }
        }

        $numbers = range(1, count($gridQuestions));
        shuffle($numbers);

        foreach ($gridQuestions as $question) {
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
