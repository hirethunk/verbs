<?php

namespace Thunk\Verbs\Exceptions;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Thunk\Verbs\Support\NamedDependency;
use Thunk\Verbs\Support\Reflection\Parameter;

class AmbiguousDependencyException extends InvalidArgumentException
{
    public function __construct(Parameter $parameter, Collection $candidates)
    {
        // TODO: We might want to link to docs here, although the message is very descriptive

        $variable = '$'.$parameter->getName();
        $typehint = class_basename($parameter->type()->name());

        $message = "Unable to resolve dependency '{$typehint} {$variable}' because '{$typehint}' is ambiguous.";
        $message = $this->appendCandidateList($message, $variable, $candidates);

        parent::__construct($message);
    }

    protected function appendCandidateList(string $message, string $variable, Collection $candidates): string
    {
        $options = $candidates
            ->filter(fn ($candidate) => $candidate instanceof NamedDependency)
            ->map(fn (NamedDependency $candidate) => "'\${$candidate->name}'");

        if ($options->isNotEmpty()) {
            $message .= " Did you mean {$options->implode(' or ')} instead of '{$variable}'?";
        }

        return $message;
    }
}
