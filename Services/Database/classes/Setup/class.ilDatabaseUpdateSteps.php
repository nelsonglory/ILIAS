<?php

/* Copyright (c) 2019 Richard Klees <richard.klees@concepts-and-training.de> Extended GPL, see docs/LICENSE */

use ILIAS\Setup\Environment;
use ILIAS\Setup\CallableObjective;
use ILIAS\Setup\NullObjective;
use ILIAS\Setup\Objective;


/**
 * This base-class simplifies the creation of (consecutive) database updates.
 *
 * Implement update steps on one or more tables by creating methods that follow
 * this schema:
 *
 * public function step_1(\ilDBInterface $db) { ... }
 *
 * The class will figure out which of them haven't been performed yet and need
 * to be executed.
 *
 * If the class takes care of only one table or a set of related tables it will
 * be easier to maintain.
 *
 * If for some reason you rely on update steps from other db-updated-classes
 * implement `getPreconditionSteps`.
 */
abstract class ilDatabaseUpdateSteps implements Objective {
	const STEP_METHOD_PREFIX = "step_";

	/**
	 * @var	string[]|null
	 */
	protected $steps = null;

	/**
	 * @var	Objective
	 */
	protected $base;

	/**
	 * @param \ilObjective $base for the update steps, i.e. the objective that should
	 *                           have been reached before the steps of this class can
	 *                           even begin. Most propably this should be
	 *                           \ilDatabasePopulatedObjective.
	 */
	public function __construct(
		Objective $base
	) {
		$this->base = $base;
	}

	/**
	 * The hash for the objective is calculated over the classname and the steps
	 * that are contained.
	 */
	final public function getHash() : string {
		return hash(
			"sha256",
			get_class($this)
		);
	}

	final public function getLabel() : string {
		return "Database update steps in ".get_class($this);
	}

	/**
	 * @inheritdocs
	 */
	final public function isNotable() : bool {
		return true;
	}

	/**
	 * @inheritdocs
	 */
	final public function getPreconditions(Environment $environment) : array {
		$steps = $this->getSteps();
		return [$this->getStep(array_pop($steps))];
	}

	/**
	 * @inheritdocs
	 */
	final public function achieve(Environment $environment) : Environment {
		return $environment;
	}

	/**
	 * Get a database update step.
	 *
	 * @throws \LogicException if step is unknown
	 */
	final public function getStep(int $num) : ilDatabaseUpdateStep {
		return new ilDatabaseUpdateStep(
			$this,
			self::STEP_METHOD_PREFIX.$num,
			...$this->getPreconditionsOfStep($num)
		);
	}

	/**
	 * @return Objective[]
	 */
	final protected function getPreconditionsOfStep(int $num) : array {
		$others = $this->getStepsBefore($num);
		if (count($others) === 0) {
			return [$this->base];
		}
		return [$this->getStep(array_pop($others))];
	}

	/**
	 * Get the names of the step-methods in this class.
	 *
	 * @return int[]
	 */
	final protected function getSteps() : array {
		if (!is_null($this->steps)) {
			return $this->steps;
		}

		$this->steps = [];

		foreach (get_class_methods(static::class) as $method) {
			if (stripos($method, self::STEP_METHOD_PREFIX) !== 0) {
				continue;
			}

			$number = substr($method, strlen(self::STEP_METHOD_PREFIX));

			if (!preg_match("/^[1-9]\d*$/", $number)) {
				throw new \LogicException("Method $method seems to be a step but has an odd looking number");
			}

			$this->steps[(int)$number] = (int)$number;
		}

		asort($this->steps);

		return $this->steps;
	}

	/**
	 * Get the names of the step-methods before the given step.
	 *
	 * ATTENTION: The steps are sorted in ascending order.
	 *
	 * @throws \LogicException if step is not known
	 * @return int[]
	 */
	final protected function getStepsBefore(int $num) {
		$this->getSteps();
		if (!isset($this->steps[$num])) {
			throw new \LogicException("Unknown database update step: $num");
		}

		$res = [];
		foreach ($this->steps as $cur) {
			if ($cur === $num) {
				break;
			}
			$res[$cur] = $cur;
		}
		return $res;
	}
}