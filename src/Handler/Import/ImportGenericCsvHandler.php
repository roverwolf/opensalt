<?php

namespace App\Handler\Import;

use App\Command\Import\ImportGenericCsvCommand;
use App\Event\CommandEvent;
use App\Event\NotificationEvent;
use App\Handler\BaseDoctrineHandler;
use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ImportGenericCsvHandler extends BaseDoctrineHandler
{
    public function handle(CommandEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        /** @var ImportGenericCsvCommand $command */
        $command = $event->getCommand();
        $this->validate($command, $command);

        $filePath = $command->getFilePath();
        $creator = $command->getCreator() ?? 'System';
        $title = $command->getTitle() ?? 'Imported CSV';
        $organization = $command->getOrganization();

        $doc = $this->importCsv($filePath, $title, $creator);

        if ($organization) {
            $doc->setOrg($organization);
        }

        $notification = new NotificationEvent(
            'D10',
            sprintf('Framework "%s" imported via CSV', $doc->getTitle()),
            $doc,
            [
                'doc-a' => [
                    $doc,
                ],
            ]
        );
        $command->setNotificationEvent($notification);
    }

    private function importCsv(string $filename, string $title, string $creator): LsDoc
    {
        //  CSV columns: Type, Statement, Coding, Parent
        $fd = fopen($filename, 'rb');

        $doc = new LsDoc();
        $doc->setTitle($title);
        $doc->setCreator($creator);

        $this->em->persist($doc);

        $itemTypes = $this->em->getRepository(LsDefItemType::class)->getList();
        $items = [];

        // Ignore first row (assuming it is a header)
        fgetcsv($fd, 0, ',');

        $i = 1;
        while (FALSE !== ($rec = fgetcsv($fd, 0, ','))) {
            $item = new LsItem();
            $item->setLsDoc($doc);

            if (empty($itemTypes[$rec[0]])) {
                $itemType = new LsDefItemType();
                $itemType->setCode($rec[0]);
                $itemType->setTitle($rec[0]);
                $itemType->setHierarchyCode($rec[0]);

                $this->em->persist($itemType);
                $itemTypes[$rec[0]] = $itemType;
            }
            $item->setItemType($itemTypes[$rec[0]]);
            $item->setFullStatement($rec[1]);
            $item->setHumanCodingScheme($rec[2]);
            $item->setListEnumInSource($i);

            if (!empty($rec[3]) && !empty($items[$rec[3]])) {
                $item->addParent($items[$rec[3]], $i++);
            } else {
                $item->addParent($doc, $i++);
            }

            $item->setAbbreviatedStatement($rec[4] ?? null);
            $item->setEducationalAlignment($this->normalizeGrades($rec[5] ?? ''));

            $this->em->persist($item);

            $items[$rec[2]] = $item;
        }
        fclose($fd);

        return $doc;
    }

    protected function normalizeGrades(string $gradeString): ?string
    {
        $gradeString = trim($gradeString);
        if (empty($gradeString)) {
            return null;
        }

        $grades = [];
        foreach (preg_split('/\s*,\s*/', $gradeString) as $grade) {
            $grades = $this->parseGrade($grade, $grades);
        }

        if (0 < count($grades)) {
            return implode(',', $grades);
        }

        return null;
    }

    protected function parseGrade(string $grade, array $grades): array
    {
        if (empty($grade)) {
            return $grades;
        }

        if (in_array($grade, [0, '0', '00', 'K', 'KG'], true)) {
            $grades['KG'] = 'KG';

            return $grades;
        }

        if ('HS' === $grade) {
            $grades['09'] = '09';
            $grades['10'] = '10';
            $grades['11'] = '11';
            $grades['12'] = '12';

            return $grades;
        }

        if (in_array(
            $grade,
            [
                'IT', 'PR', 'PK', 'TK', 'KG', 'AS', 'BA',
                'PB', 'MD', 'PM', 'DO', 'PD', 'AE', 'PT',
                'OT',
            ],
            true
        )) {
            $grades[$grade] = $grade;

            return $grades;
        }

        if (is_numeric($grade)) {
            if (10 > $grade) {
                $grade = '0'.((int) $grade);
                $grades[$grade] = $grade;

                return $grades;
            }

            if (14 > $grade) {
                $grade = (int) $grade;
                $grades["{$grade}"] = $grade;

                return $grades;
            }
        }

        $grades['OT'] = 'OT';

        return $grades;
    }
}
