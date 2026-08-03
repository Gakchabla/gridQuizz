<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;

// Called when a player clicks a grid number. Normally just marks the question
// answered — but if the picking player isn't in "mode facile" themselves and
// the question belongs to a theme whose player IS in mode facile, they're
// "stealing" an easy question: swap in a harder one from the hardcore reserve
// instead (same number, so the grid doesn't change), and give the original
// question back to the reserve (number cleared) so it can't be picked again
// this game.
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

        if ($question->isAnswered()) {
            return $question;
        }

        $activeQuestion = $this->maybeSwapForHardcore($question);
        $activeQuestion->setAnswered(true);
        $this->entityManager->flush();

        return $activeQuestion;
    }

    private function maybeSwapForHardcore(Question $question): Question
    {
        $session = $question->getSession();
        $currentPlayer = $session->getCurrentPlayer();

        if (null === $currentPlayer || $currentPlayer->isEasyMode()) {
            return $question;
        }

        $theme = $question->getTheme();
        $owner = null;
        foreach ($session->getPlayers() as $player) {
            if ($player->getTheme() === $theme) {
                $owner = $player;
                break;
            }
        }

        if (null === $owner || !$owner->isEasyMode()) {
            return $question;
        }

        $hardcoreQuestion = $this->entityManager->createQueryBuilder()
            ->select('q')
            ->from(Question::class, 'q')
            ->join('q.theme', 't')
            ->where('q.session = :session')
            ->andWhere('t.hardcore = true')
            ->andWhere('q.number IS NULL')
            ->andWhere('q.answered = false')
            ->setParameter('session', $session)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Reserve exhausted (more steals than hardcore questions available):
        // fall back to the original question rather than failing the pick.
        if (null === $hardcoreQuestion) {
            return $question;
        }

        $hardcoreQuestion->setNumber($question->getNumber());
        $question->setNumber(null);

        return $hardcoreQuestion;
    }
}
