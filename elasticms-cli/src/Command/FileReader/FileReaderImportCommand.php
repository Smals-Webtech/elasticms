<?php

declare(strict_types=1);

namespace App\CLI\Command\FileReader;

use App\CLI\Commands;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Contracts\File\FileReaderInterface;
use EMS\CommonBundle\Search\Search;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class FileReaderImportCommand extends AbstractCommand
{
    protected static $defaultName = Commands::FILE_READER_IMPORT;

    private const ARGUMENT_FILE = 'file';
    private const ARGUMENT_CONTENT_TYPE = 'content-type';
    private const OPTION_OUUID_EXPRESSION = 'ouuid-expression';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_GENERATE_HASH = 'generate-hash';
    private const OPTION_DELETE_MISSING_DOCUMENTS = 'delete-missing-document';
    private string $ouuidExpression;
    private string $contentType;
    private string $file;
    private bool $dryRun;
    private bool $hashOuuid;
    private bool $deleteMissingDocuments;

    public function __construct(private readonly AdminHelper $adminHelper, private readonly FileReaderInterface $fileReader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import an Excel file or a CSV file, one document per row')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'File path (xlsx or csv)')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'Content type target')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Just do a dry run')
            ->addOption(self::OPTION_GENERATE_HASH, null, InputOption::VALUE_NONE, 'Use the OUUID column and the content type name in order to generate a "better" ouuid')
            ->addOption(self::OPTION_DELETE_MISSING_DOCUMENTS, null, InputOption::VALUE_NONE, 'The command will delete content type document that are missing in the import file')
            ->addOption(self::OPTION_OUUID_EXPRESSION, null, InputOption::VALUE_OPTIONAL, 'Expression language apply to excel rows in order to identify the document by its ouuid. If equal to null new document will be created', "row['ouuid']")
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->file = $this->getArgumentString(self::ARGUMENT_FILE);
        $this->contentType = $this->getArgumentString(self::ARGUMENT_CONTENT_TYPE);
        $this->ouuidExpression = $this->getOptionString(self::OPTION_OUUID_EXPRESSION);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
        $this->hashOuuid = $this->getOptionBool(self::OPTION_GENERATE_HASH);
        $this->deleteMissingDocuments = $this->getOptionBool(self::OPTION_DELETE_MISSING_DOCUMENTS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMS Client - File reader importer');
        $coreApi = $this->adminHelper->getCoreApi();
        $contentTypeApi = $coreApi->data($this->contentType);

        if (!$coreApi->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $expressionLanguage = new ExpressionLanguage();
        $rows = $this->fileReader->getData($this->file);
        $header = $rows[0] ?? [];

        $ouuids = [];
        if ($this->deleteMissingDocuments) {
            $defaultAlias = $this->adminHelper->getCoreApi()->meta()->getDefaultContentTypeEnvironmentAlias($this->contentType);
            $search = new Search([$defaultAlias]);
            $search->setSources(['_id']);
            $search->setContentTypes([$this->contentType]);

            foreach ($this->adminHelper->getCoreApi()->search()->scroll($search) as $hit) {
                $ouuids[$hit->getOuuid()] = true;
            }
        }

        $progressBar = $this->io->createProgressBar(\count($rows) - 1);
        foreach ($rows as $key => $value) {
            if (0 === $key) {
                continue;
            }
            $row = [];
            foreach ($value as $key => $cell) {
                $row[$header[$key] ?? $key] = $cell;
            }

            $ouuid = 'null' === $this->ouuidExpression ? null : $expressionLanguage->evaluate($this->ouuidExpression, [
                'row' => $row,
            ]);
            if ('null' !== $this->ouuidExpression && $this->hashOuuid) {
                $ouuid = \sha1(\sprintf('FileReaderImport:%s:%s', $this->contentType, $ouuid));
            }
            unset($ouuids[$ouuid]);

            if ($this->dryRun) {
                $progressBar->advance();
                continue;
            }

            if ('null' === $this->ouuidExpression) {
                $draft = $contentTypeApi->create([
                    '_sync_metadata' => $row,
                ]);
            } elseif ($contentTypeApi->head($ouuid)) {
                $draft = $contentTypeApi->update($ouuid, [
                    '_sync_metadata' => $row,
                ]);
            } else {
                $draft = $contentTypeApi->create([
                    '_sync_metadata' => $row,
                ], $ouuid);
            }
            $contentTypeApi->finalize($draft->getRevisionId());
            $progressBar->advance();
        }
        $progressBar->finish();

        if ($this->dryRun && \count($ouuids) > 0) {
            $this->io->newLine(2);
            $this->io->warning(\sprintf('%d documents are missing in the source file and will be deleted without the %s option', \count($ouuids), self::OPTION_DRY_RUN));
        } elseif (\count($ouuids) > 0) {
            $this->io->newLine(2);
            $this->io->section(\sprintf('%d documents have not been updated and will be deleted', \count($ouuids)));
            $progressBar = $this->io->createProgressBar(\count($ouuids));
            foreach ($ouuids as $ouuid => $data) {
                $contentTypeApi->delete($ouuid);
                $progressBar->advance();
            }
            $progressBar->finish();
        }

        return self::EXECUTE_SUCCESS;
    }
}
