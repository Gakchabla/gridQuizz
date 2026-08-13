<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// Data migration (not a schema change): seeds the "Quizz" session created via
// the app (9 themed rounds + a "Bonus" round, one player per normal theme,
// "Bluey" in mode facile) so it ships with the app instead of living only in
// this dev's database. IDs are fixed in a high, dedicated range (90000+) to
// stay clear of whatever a real deployment's sequences have reached.
// Questions are inserted with number = NULL / answered = false (never played)
// — ResetSessionProcessor assigns grid numbers on first "Jouer" click, same as
// any freshly created session.
final class Version20260813120000 extends AbstractMigration
{
    private const SESSION_ID = 90000;

    public function getDescription(): string
    {
        return 'Seed the "Quizz" session (9 themed rounds + bonus, from Quizz.docx)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO session (id, name, shuffled, reveal_duration, created_at) VALUES (?, ?, ?, ?, ?)',
            [self::SESSION_ID, 'Quizz', true, 15, '2026-08-13 11:24:18'],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::INTEGER, ParameterType::STRING],
        );

        // [id, name, color, bonus]
        $themes = [
            [90001, 'Le nom du vent', '#da1a69', false],
            [90002, "Chansons françaises de l'année 2000", '#6b727a', false],
            [90003, 'Philippe Etchebest', '#e84415', false],
            [90004, 'Calvin et Hobbes', '#e2b936', false],
            [90005, 'Un indien dans la ville', '#569f18', false],
            [90006, 'Les films de Jackie Chan', '#1fab90', false],
            [90007, 'Kaamelott', '#1ea1d2', false],
            [90008, 'Les animaux marins', '#f96caa', false],
            [90009, 'Bluey', '#e51eb9', false],
            [90010, 'Bonus', 'rainbow', true],
        ];
        foreach ($themes as [$id, $name, $color, $bonus]) {
            $this->addSql(
                'INSERT INTO theme (id, session_id, name, color, bonus, hardcore) VALUES (?, ?, ?, ?, ?, false)',
                [$id, self::SESSION_ID, $name, $color, $bonus],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN],
            );
        }

        // [id, theme_id, name, easy_mode]
        $players = [
            [90001, 90001, 'Joanne', false],
            [90002, 90002, 'Angelique', false],
            [90003, 90003, 'Nico', false],
            [90004, 90004, 'Gus', false],
            [90005, 90005, 'Alison', false],
            [90006, 90006, 'Jib', false],
            [90007, 90007, 'Armand', false],
            [90008, 90008, 'Prisci', false],
            [90009, 90009, 'Juju', true],
        ];
        foreach ($players as [$id, $themeId, $name, $easyMode]) {
            $this->addSql(
                'INSERT INTO player (id, session_id, theme_id, name, score, easy_mode) VALUES (?, ?, ?, ?, 0, ?)',
                [$id, self::SESSION_ID, $themeId, $name, $easyMode],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::BOOLEAN],
            );
        }

        // [id, theme_id, questionText, answerText]
        $questions = [
            // Le nom du vent (90001)
            [90001, 90001, "Quels sont les noms des 2 amis et du pire ennemi de Kvothe à l'université ?", 'Simmon, Willem et Ambrose'],
            [90002, 90001, "Comment s'appelle le jeu de carte auquel jouent Kvothe et ses amis ?", 'Les coins (Tak)'],
            [90003, 90001, "Quel est le nom de la lumière d'Auri ?", 'Foxen (Foxin)'],
            [90004, 90001, 'Quelles sont les origines de Simon, Willem et Ambrose ?', 'Simon -> Aturan, Willem -> Cealdish, Ambrose -> Modegan'],
            [90005, 90001, 'Donne le nom de 4 des 7 Chandrians.', 'Cyphus, Stercus, Ferule (Cinder), Usnea, Dalcenti, Alenta, Alaxel (Haliax)'],
            // Chansons françaises de l'année 2000 (90002)
            [90006, 90002, 'Durant quel événement "on drague, on branche, toi même tu sais pourquoi" ?', 'Ces soirées-là'],
            [90007, 90002, "Avant de partir, que devrait-on laisser à Eve Angeli ?", 'Un peu de moi/toi'],
            [90008, 90002, 'Selon Tina Arena, de quoi se rapproche-t-on en allant plus haut ?', "L'avenir"],
            [90009, 90002, 'Cite 4 artistes/groupes francophones parmi les 10 ayant eu le plus longtemps un titre dans le top 10 hebdomadaire français.', 'À vérifier'],
            [90010, 90002, "Quel album francophone est le plus vendu de l'année ?", 'Roméo et Juliette, de la haine à l\'amour'],
            // Philippe Etchebest (90003)
            [90011, 90003, 'Dans quelle émission est-il juré ?', 'Top Chef'],
            [90012, 90003, 'Dans quelle émission peut-on le voir engueuler des restaurateurs en déroute ?', 'Cauchemar en cuisine'],
            [90013, 90003, 'Quel concours national remporte-t-il en 2000 ?', 'MOF (Meilleur Ouvrier de France)'],
            [90014, 90003, '"C\'est qui le patron ?!"', "C'est moi"],
            [90015, 90003, 'Quel est le nom du groupe de rock dans lequel il joue de la batterie ?', 'Chef and the Gang'],
            // Calvin et Hobbes (90004)
            [90016, 90004, 'Qui, de Calvin et Hobbes, est un tigre ?', 'Hobbes'],
            [90017, 90004, "Comment s'appelle le camarade de classe qui martyrise Calvin ?", 'Moe'],
            [90018, 90004, 'Qui ont inspiré les noms des deux personnages principaux ?', 'Jean Calvin et Thomas Hobbes, des philosophes'],
            [90019, 90004, "Quel sujet passionne Calvin en dehors de l'école ?", 'Les dinosaures'],
            [90020, 90004, "Comment s'appelle le club anti-filles créé par Calvin et Hobbes ?", 'Dehors Énormes Filles Informes (G.R.O.S.S.)'],
            // Un indien dans la ville (90005)
            [90021, 90005, "Comment s'appelle l'indien qui est effectivement dans la ville ?", 'Mimi-Siku'],
            [90022, 90005, "Richard (Patrick Timsit) n'a pas vendu les 4500 tonnes de soja comme convenu — selon ses propres termes, comment est-on ?", 'On est mal on est mal'],
            [90023, 90005, 'Quelle chanson culte accompagne le film ?', 'Chacun sa route - Tonton David'],
            [90024, 90005, 'Quel rêve Mimi-Siku veut-il accomplir en suivant son père ?', 'Voir la tour Eiffel'],
            [90025, 90005, "Combien d'entrées au cinéma le film a-t-il fait en France ?", "7,88 millions d'entrées"],
            // Les films de Jackie Chan (90006)
            [90026, 90006, 'Autour de quelle pratique sportive tourne la quasi-totalité des films de Jackie Chan ?', 'Les arts martiaux'],
            [90027, 90006, "Dans quelle série de films partage-t-il l'affiche avec Chris Tucker ?", 'Rush Hour'],
            [90028, 90006, "Dans un film de 2002, qu'enfile-t-il pour obtenir une force surhumaine ?", 'Un smoking'],
            [90029, 90006, 'Quelle est la particularité de son style de combat dans "Le Maître chinois" ?', 'Il ne se bat que quand il est ivre'],
            [90030, 90006, "Combien de films a-t-il réalisés ?", '16'],
            // Kaamelott (90007)
            [90031, 90007, "Quelle phrase Perceval et Caradoc utilisent-ils quand ils n'ont pas compris quelque chose ?", "C'est pas faux"],
            [90032, 90007, 'Que cuisine dame Séli pour ses futurs petits-enfants ?', 'Des tartes'],
            [90033, 90007, 'Qui ponctue souvent ses phrases de citations latines qui ne veulent rien dire ?', 'Le roi Loth'],
            [90034, 90007, 'Que rajoute toujours Perceval dans les récits de ses aventures ?', "Un p'tit vieux"],
            [90035, 90007, 'Cite la présentation de Merlin lors du duel contre Elias.', 'Enchanteur de Bretagne, grand vainqueur de la belette de Winchester, concepteur de la potion de guérison des ongles incarnés, auteur du parchemin "le druidisme expliqué aux personnes âgées"'],
            // Les animaux marins (90008)
            [90036, 90008, 'De quelle famille de mammifères aquatiques font partie les baleines ?', 'Les cétacés'],
            [90037, 90008, 'Avec quelle espèce de poisson les anémones entretiennent-elles une relation symbiotique ?', 'Le poisson-clown'],
            [90038, 90008, 'Quel est le plus gros mammifère marin ?', 'La baleine bleue'],
            [90039, 90008, 'Quel animal marin porte un nom qui se traduit par « une dent, une corne » en grec ?', 'Le narval'],
            [90040, 90008, 'Quel est le plus petit mammifère marin ?', 'La loutre de mer'],
            // Bluey (90009)
            [90041, 90009, "Comment s'appelle la sœur de Bluey ?", 'Bingo'],
            [90042, 90009, "Comment s'appellent les parents de Bluey ?", 'Bandit et Chili'],
            [90043, 90009, 'De quelle couleur est Bluey ?', 'Bleu'],
            [90044, 90009, "Donne le nom d'un ami d'école de Bluey.", 'Mackenzie, Chloé, Honey, Coco ou Snickers'],
            [90045, 90009, 'Combien de chiens y a-t-il dans la famille de Bluey ?', '4'],
            // Bonus (90010)
            [90046, 90010, "Cite, en italien, 3 aliments qu'on retrouve souvent sur une pizza.", "(réponse libre — +1 point pour l'accent italien)"],
            [90047, 90010, 'De quelle ville la pizza est-elle originaire ?', 'Naples'],
            [90048, 90010, 'Quelle recette de pâtes se compose de sauce tomate, oignon, guanciale et pecorino ?', "All'amatriciana"],
            [90049, 90010, 'Quelle pizza classique est composée de tomate, anchois, câpres, oignon et olives ?', 'Napolitaine'],
            [90050, 90010, "Quel chef est célèbre notamment pour une soupe portant le nom d'un ancien président ?", 'Bocuse (soupe VGE)'],
            [90051, 90010, "Quel est l'ingrédient principal du palak paneer ?", "L'épinard"],
            [90052, 90010, "Quel est l'ingrédient principal du baba ganoush ?", "L'aubergine"],
            [90053, 90010, 'Quelle épice est prédominante dans la cuisine hongroise ?', 'Le paprika'],
            [90054, 90010, 'Selon Taste Atlas, quel pays a la meilleure cuisine au monde ?', "L'Italie (la France est 7ᵉ)"],
        ];
        foreach ($questions as [$id, $themeId, $questionText, $answerText]) {
            $this->addSql(
                'INSERT INTO question (id, session_id, theme_id, number, question_text, answer_text, answered) VALUES (?, ?, ?, NULL, ?, ?, false)',
                [$id, self::SESSION_ID, $themeId, $questionText, $answerText],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Cascades to theme/question/player via their session_id FK (ON DELETE CASCADE).
        $this->addSql('DELETE FROM session WHERE id = ?', [self::SESSION_ID], [ParameterType::INTEGER]);
    }
}
