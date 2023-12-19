<?php

namespace Thunk\Verbs\Examples\Monopoly\Support;

use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Thunk\Verbs\Examples\Monopoly\Game\Bank;
use Thunk\Verbs\Examples\Monopoly\Game\Board;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\State;

class MoneyNormalizer implements DenormalizerInterface, NormalizerInterface
{
	public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
	{
		return is_a($type, Money::class, true);
	}
	
	/** @param class-string<Money> $type */
	public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Money
	{
		return Money::ofMinor($data['amount'], $data['currency']);
	}
	
	public function supportsNormalization(mixed $data, ?string $format = null): bool
	{
		return $data instanceof Money;
	}
	
	public function normalize(mixed $object, ?string $format = null, array $context = []): array
	{
		if (! $object instanceof Money) {
			throw new InvalidArgumentException(class_basename($this).' can only normalize Money objects.');
		}
		
		return [
			'amount' => (string) $object->getMinorAmount(), 
			'currency' => $object->getCurrency()->getCurrencyCode()
		];
	}
	
	public function getSupportedTypes(?string $format): array
	{
		return [Money::class => false,];
	}
}
