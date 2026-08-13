<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

// Called when a player clicks a grid number: marks the question answered.
// Easy-mode themes are locked client-side (GameView.vue) for every player but
// their own owner, so no server-side steal handling is needed here anymore.
/** @implements ProcessorInterface<Question, Question> */
final class SelectQuestionProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Question
    {
        /** @var Question $question */
        $question = $data;

        if (!$question->isAnswered()) {
            $question->setAnswered(true);
            $this->entityManager->flush();
        }

        return $question;
    }
}
