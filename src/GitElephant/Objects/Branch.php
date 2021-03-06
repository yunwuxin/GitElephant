<?php

/**
 * GitElephant - An abstraction layer for git written in PHP
 * Copyright (C) 2013  Matteo Giachino
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see [http://www.gnu.org/licenses/].
 */

namespace GitElephant\Objects;

use GitElephant\Command\BranchCommand;
use GitElephant\Exception\InvalidBranchNameException;
use GitElephant\Repository;

/**
 * An object representing a git branch
 *
 * @author Matteo Giachino <matteog@gmail.com>
 */
class Branch extends NodeObject implements TreeishInterface
{
    /**
     * current checked out branch
     *
     * @var bool
     */
    private $current = false;

    /**
     * branch name
     *
     * @var string
     */
    private $name;

    /**
     * sha
     *
     * @var string
     */
    private $sha;

    /**
     * branch comment
     *
     * @var string
     */
    private $comment;

    /**
     * the full branch reference
     *
     * @var string
     */
    private $fullRef;

    /**
     * Creates a new branch on the repository and returns it
     *
     * @param \GitElephant\Repository $repository repository instance
     * @param string                  $name       branch name
     * @param string                  $startPoint branch to start from
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return \GitElephant\Objects\Branch
     */
    public static function create(
        Repository $repository,
        string $name,
        string $startPoint = null
    ): \GitElephant\Objects\Branch {
        /** @var BranchCommand */
        $branchCommand = BranchCommand::getInstance($repository);

        $repository
            ->getCaller()
            ->execute($branchCommand->create($name, $startPoint));

        return new self($repository, $name);
    }

    /**
     * static generator to generate a single commit from output of command.show service
     *
     * @param \GitElephant\Repository $repository repository
     * @param string                  $outputLine output line
     *
     * @throws \InvalidArgumentException
     * @return Branch
     */
    public static function createFromOutputLine(Repository $repository, string $outputLine): \GitElephant\Objects\Branch
    {
        $matches = static::getMatches($outputLine);
        $branch = new self($repository, $matches[1]);
        $branch->parseOutputLine($outputLine);

        return $branch;
    }

    /**
     * @param \GitElephant\Repository $repository repository instance
     * @param string|TreeishInterface $name       branch name
     * @param bool                    $create     like checkout -b, create a branch and check it out
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return Branch
     */
    public static function checkout(Repository $repository, $name, $create = false): \GitElephant\Objects\Branch
    {
        $branch = $create ? self::create($repository, $name) : new self($repository, $name);

        $repository->checkout($branch);

        return $branch;
    }

    /**
     * Class constructor
     *
     * @param \GitElephant\Repository $repository repository instance
     * @param string                  $name       branch name
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \GitElephant\Exception\InvalidBranchNameException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    public function __construct(Repository $repository, string $name)
    {
        $this->repository = $repository;
        $this->name = trim($name);
        $this->fullRef = 'refs/heads/' . $name;
        $this->createFromCommand();
    }

    /**
     * get the branch properties from command
     *
     * @throws \InvalidArgumentException
     */
    private function createFromCommand(): void
    {
        $command = BranchCommand::getInstance($this->getRepository())->listBranches();
        $outputLines = $this->repository->getCaller()->execute($command)->getOutputLines(true);
        foreach ($outputLines as $outputLine) {
            $matches = static::getMatches($outputLine);

            if ($this->name === $matches[1]) {
                $this->parseOutputLine($outputLine);

                return;
            }
        }

        throw new InvalidBranchNameException(sprintf('The %s branch doesn\'t exists', $this->name));
    }

    /**
     * parse an output line from the BranchCommand::singleInfo command
     *
     * @param string $branchString an output line for a branch
     *
     * @throws \InvalidArgumentException
     */
    public function parseOutputLine(string $branchString): void
    {
        if (preg_match('/^\* (.*)/', $branchString, $matches)) {
            $this->current = true;
            $branchString = substr($branchString, 2);
        } else {
            $branchString = trim($branchString);
        }

        $matches = static::getMatches($branchString);
        $this->name = $matches[1];
        $this->sha = $matches[2];
        $this->comment = $matches[3];
    }

    /**
     * get the matches from an output line
     *
     * @param string $branchString branch line output
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function getMatches(string $branchString): array
    {
        $branchString = trim($branchString);
        $regexList = [
            '/^\*?\ *?(\S+)\ +(\S{40})\ +(.+)$/',
            '/^\*?\ *?\(.*(detached).*\)\ +(\S{40})\ +(.+)$/',
        ];

        $matches = [];
        while (empty($matches) and $regex = array_pop($regexList)) {
            preg_match($regex, trim($branchString), $matches);
        }

        if (empty($matches)) {
            throw new \InvalidArgumentException(sprintf('the branch string is not valid: %s', $branchString));
        }

        return array_map('trim', $matches);
    }

    /**
     * toString magic method
     *
     * @return string the sha
     */
    public function __toString(): string
    {
        return $this->getSha();
    }

    /**
     * name setter
     *
     * @param string $name the branch name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * name setter
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * sha setter
     *
     * @param string $sha the sha of the branch
     */
    public function setSha(string $sha): void
    {
        $this->sha = $sha;
    }

    /**
     * sha getter
     *
     * @return string
     */
    public function getSha(): string
    {
        return $this->sha;
    }

    /**
     * current setter
     *
     * @param bool $current whether if the branch is the current or not
     */
    public function setCurrent(bool $current): void
    {
        $this->current = $current;
    }

    /**
     * current getter
     *
     * @return bool
     */
    public function getCurrent(): bool
    {
        return $this->current;
    }

    /**
     * comment setter
     *
     * @param string $comment the branch comment
     */
    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * comment getter
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * fullref setter
     *
     * @param string $fullRef full git reference of the branch
     */
    public function setFullRef(string $fullRef): void
    {
        $this->fullRef = $fullRef;
    }

    /**
     * fullRef getter
     *
     * @return string
     */
    public function getFullRef(): string
    {
        return $this->fullRef;
    }
}
