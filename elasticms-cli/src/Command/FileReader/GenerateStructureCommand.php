<?php

declare(strict_types=1);

namespace App\CLI\Command\FileReader;

use App\CLI\Client\HttpClient\CacheManager;
use App\CLI\Client\WebToElasticms\Helper\Url;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Common\SpreadsheetGeneratorService;
use EMS\CommonBundle\Contracts\File\FileReaderInterface;
use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CommonBundle\Search\Search;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class GenerateStructureCommand extends AbstractCommand
{
    protected static $defaultName = 'emscli:file-reader:generate-structure';
    final public const FIELD_URL = 0;
    final public const FIELD_TITLE = 1;
    final public const FIELD_ID = 2;
    final public const FIELD_TYPE = 3;
    final public const FIELD_PARENT_ID = 4;
    final public const FIELD_SORT_ORDER = 5;
    final public const FIELD_HIDDEN = 6;
    final public const BASE_URL = 'https://www.inami.fgov.be';

    private const ARGUMENT_FILE = 'file';
    private string $file;
    /** @var string[][] */
    private array $brothers;
    /** @var string[][] */
    private array $logs = [['URL', 'Message', 'Type']];
    /** @var string[][] */
    private array $hitByPath;
    private SpreadsheetGeneratorService $spreadsheetGeneratorService;
    private JsonMenuNested $menu;
    private CacheManager $cacheManager;

    public function __construct(private readonly AdminHelper $adminHelper, private readonly FileReaderInterface $fileReader)
    {
        parent::__construct();
        $this->spreadsheetGeneratorService = new SpreadsheetGeneratorService();
        $this->menu = JsonMenuNested::fromStructure('{}');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Source file to convert into structure')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'File path (xlsx or csv)')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->file = $this->getArgumentString(self::ARGUMENT_FILE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMS Client - Generate structure');

        $coreApi = $this->adminHelper->getCoreApi();

        $this->cacheManager = new CacheManager(\implode(DIRECTORY_SEPARATOR, [\getcwd(), 'var']), false);

        if (!$coreApi->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $this->loadOuuids();

        $rows = $this->fileReader->getData($this->file);
        $this->io->progressStart(\count($rows) - 1);

        if ($rows[0] !== ['Url', 'Title', 'Id', 'Type', 'ParentId', 'SortOrder', 'Hidden']) {
            throw new \RuntimeException('Unexpected excel structure');
        }
        $this->brothers = [];
        \array_shift($rows);
        foreach ($rows as $value) {
            $sortOrder = \intval($value[self::FIELD_SORT_ORDER]);
            if (1 === $sortOrder) {
                $this->treatBrotherhood();
                $this->brothers = [];
            }
            $this->brothers[] = $value;
            $this->io->progressAdvance();
        }
        $this->treatBrotherhood();
        $this->io->progressFinish();
        $this->generateRapport();

        \file_put_contents('menu.json', Json::encode(Json::encode($this->menu->toArrayStructure())));

        return self::EXECUTE_SUCCESS;
    }

    private function logWarning(string $url, string $message, string $type): void
    {
        $this->io->warning(\sprintf('URL %s: %s', $url, $message));
        $this->logs[] = [$url, $message, $type];
    }

    private function treatBrotherhood(): void
    {
        if (0 === \count($this->brothers)) {
            return;
        }
        $this->io->text(\sprintf('This brotherhood contains %d urls', \count($this->brothers)));
        $firstPage = null;
        $children = [];
        $alreadyTreated = [];
        foreach ($this->brothers as $id => $row) {
            $result = $this->cacheManager->get(self::BASE_URL.$row[self::FIELD_URL]);
            if (!$result->hasResponse()) {
                throw new \RuntimeException('Response is missing');
            }
            $code = $result->getResponse()->getStatusCode();

            if (\in_array($code, [301, 302])) {
                $redirect = $result->getResponse()->getHeaders()['Location'][0] ?? null;
                if (null === $redirect) {
                    $this->logWarning($row[self::FIELD_URL], 'Redirect to null', 'null_redirect');
                    continue;
                }
                $url = new Url($redirect);
                if (!isset($alreadyTreated[$url->getPath()])) {
                    $alreadyTreated[$url->getPath()] = true;
                    $row[self::FIELD_URL] = $url->getPath();
                    $result = $this->cacheManager->get($redirect);
                    if (!$result->hasResponse()) {
                        throw new \RuntimeException('Response is missing');
                    }
                    $code = $result->getResponse()->getStatusCode();
                }
            }

            if ($code >= 400) {
                $this->logWarning($row[self::FIELD_URL], $result->getErrorMessage() ?? 'Url in error', 'http_error');
                continue;
            } elseif (\in_array($code, [301, 302])) {
                $this->logWarning($row[self::FIELD_URL], \sprintf('Redirect to %s', $result->getResponse()->getHeaders()['Location'][0]), 'redirect');
                continue;
            } elseif (200 !== $code) {
                $this->logWarning($row[self::FIELD_URL], \sprintf('Not supported code %d', $code), 'code_not_supported');
                continue;
            }
            if (!isset($this->hitByPath[$row[self::FIELD_URL]])) {
                $this->logWarning($row[self::FIELD_URL], 'Not imported in elasticMS', 'not-imported');
                continue;
            }
            if (null === $firstPage && \in_array($row[self::FIELD_TYPE], ['Area', 'Page'])) {
                $firstPage = $row;
            }
            $children[] = $row;
        }

        if (0 === \count($children)) {
            return;
        }

        if (null === $firstPage) {
            $this->logWarning($this->brothers[0][self::FIELD_URL], 'Impossible to identify the parent: no page in menu', 'no_page_in_menu');

            return;
        }

        $result = $this->cacheManager->get(self::BASE_URL.$firstPage[self::FIELD_URL]);
        $crawler = new Crawler($result->getResponse()->getBody()->getContents());
        $nodes = $crawler->filter('.breadcrumb a');

        if (0 === $nodes->count()) {
            $parent = $this->menu;
        } else {
            $href = $nodes->last()->attr('href');
            if (null === $href) {
                $this->logWarning($firstPage[self::FIELD_URL], 'Parent without url', 'parent_without_url');

                return;
            }
            $parentUrl = new Url($href);
            if (!isset($this->hitByPath[$parentUrl->getPath()])) {
                $this->logWarning($firstPage[self::FIELD_URL], \sprintf('Parent %s not in the structure', $parentUrl->getPath()), 'parent_not_in_structure');

                return;
            }

            $parentMenuId = $this->generateMenuId($this->getIdByPath($parentUrl->getPath()));
            $parent = $this->menu->getItemById($parentMenuId);
            if (null === $parent) {
                $this->logWarning($parentUrl->getPath(), \sprintf('Parent not imported for %s', $firstPage[self::FIELD_URL]), 'parent_not_imported');

                return;
            }
        }

        $accessor = PropertyAccessor::createPropertyAccessor();
        foreach ($children as $child) {
            $id = $this->getIdByPath($child[self::FIELD_URL]);
            $type = $this->getTypeByPath($child[self::FIELD_URL]);
            $menuId = $this->generateMenuId($id);
            $data = $parent->getItemById($menuId) ?? null;
            if (null === $data) {
                $data = new JsonMenuNested([
                    'id' => $menuId,
                    'label' => $child[self::FIELD_TITLE],
                    'type' => $type,
                ]);
                $parent->addChild($data);
                $data = $parent->getItemById($menuId);
            } else {
                $data->setLabel(\sprintf('%s / %s', $data->getLabel(), $child[self::FIELD_TITLE]));
            }
            if (null === $data) {
                throw new \RuntimeException('Unexpected nul data');
            }
            $object = $data->getObject();
            $accessor->setValue($object, '[label]', $data->getLabel());
            $hide = $accessor->getValue($object, '[hide]');
            if (null !== $hide && $hide !== ('TRUE' === $child[self::FIELD_HIDDEN])) {
                $this->logWarning($child[self::FIELD_URL], 'Hide mismatch with siblings', 'hide_mismatch');
                $accessor->setValue($object, '[hide]', false);
            } else {
                $accessor->setValue($object, '[hide]', 'TRUE' === $child[self::FIELD_HIDDEN]);
            }
            $link = $accessor->getValue($object, \sprintf('[%s]', $data->getType()));
            if (null !== $link && $link !== \sprintf('%s:%s', $type, $id)) {
                $this->logWarning($child[self::FIELD_URL], \sprintf('Link mismatch %s', \sprintf('%s:%s', $type, $id)), 'link_mismatch');
            } else {
                $accessor->setValue($object, \sprintf('[%s]', $data->getType()), \sprintf('%s:%s', $type, $id));
            }
            $locale = \explode('/', $child[self::FIELD_URL])[1] ?? null;
            if (!\in_array($locale, ['fr', 'nl', 'de', 'en'])) {
                $this->logWarning($child[self::FIELD_URL], \sprintf('Language not identified %s', $locale), 'language_not_identified');
            } else {
                $accessor->setValue($object, \sprintf('[label_%s]', $locale), $child[self::FIELD_TITLE]);
            }
            $data->setObject($object);
        }
    }

    private function generateRapport(): void
    {
        $config = [
            SpreadsheetGeneratorServiceInterface::CONTENT_DISPOSITION => HeaderUtils::DISPOSITION_ATTACHMENT,
            SpreadsheetGeneratorServiceInterface::WRITER => SpreadsheetGeneratorServiceInterface::XLSX_WRITER,
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'Import-Report.xlsx',
            SpreadsheetGeneratorServiceInterface::SHEETS => [
                [
                    'name' => 'Warnings',
                    'rows' => $this->logs,
                ],
            ],
        ];
        $tmpFilename = 'Import-Report.xlsx';
        $this->spreadsheetGeneratorService->generateSpreadsheetFile($config, $tmpFilename);
    }

    private function loadOuuids(): void
    {
        if (\file_exists('ouuids.json')) {
            $content = \file_get_contents('ouuids.json');
            if (false === $content) {
                throw new \RuntimeException('Unexpected false content');
            }
            $this->hitByPath = Json::decode($content);

            return;
        }

        $defaultAlias = $this->adminHelper->getCoreApi()->meta()->getDefaultContentTypeEnvironmentAlias('page');
        $search = new Search([$defaultAlias]);
        $search->setSources(['fr.aspx_url', 'nl.aspx_url']);
        $search->setContentTypes(['page', 'theme', 'application', 'publication']);

        $this->hitByPath = [];

        foreach ($this->adminHelper->getCoreApi()->search()->scroll($search) as $hit) {
            $ouuid = $hit->getId();
            foreach (['fr.aspx_url', 'nl.aspx_url'] as $key) {
                $value = $hit->getValue($key);
                if (null === $value) {
                    continue;
                }
                if (isset($this->hitByPath[$value])) {
                    $this->logWarning($value, \sprintf('imported multiple time with oouid %s and ouuid %s', $this->hitByPath[$value]['id'], $ouuid), 'too_much_import');
                    continue;
                }
                $this->hitByPath[$value] = [
                    'id' => $ouuid,
                    'type' => $hit->getContentType(),
                ];
            }
        }

        \file_put_contents('ouuids.json', Json::encode($this->hitByPath));
    }

    private function getIdByPath(string $path): string
    {
        if (!isset($this->hitByPath[$path])) {
            throw new \RuntimeException(\sprintf('path %s must be in hits', $path));
        }

        return $this->hitByPath[$path]['id'];
    }

    private function getTypeByPath(string $path): string
    {
        if (!isset($this->hitByPath[$path])) {
            throw new \RuntimeException(\sprintf('path %s must be in hits', $path));
        }

        return $this->hitByPath[$path]['type'];
    }

    private function generateMenuId(string $id): string
    {
        return \sha1(\sprintf('import_structure:%s', $id));
    }
}
