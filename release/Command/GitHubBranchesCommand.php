<?php

declare(strict_types=1);

namespace EMS\Release\Command;

use EMS\Release\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitHubBranchesCommand extends AbstractGithubCommand
{
    protected static $defaultName = 'github:branches';
    protected static $defaultDescription = 'List branches for all repositories';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('GitHub: branches');

        $this->info(Config::APPLICATIONS, 'applications');
        $this->info(Config::DOCKER, 'docker');
        $this->info(Config::PACKAGES, 'packages');

        return 0;
    }

    /**
     * @param string[] $names
     */
    private function info(array $names, string $title): void
    {
        $rows = \array_map(fn (string $name) => [
            self::orgUrl($name),
            $this->getBranches($name),
        ], $names);
        $this->io->table([$title, 'branches'], $rows);
    }

    private function getBranches(string $name): string
    {
        $response = $this->githubApi->repos()->branches(self::ORG, $name);
        $branches = \array_map(fn (array $result) => $result['name'], $response);

        \rsort($branches);

        return \implode(', ', $branches);
    }
}
