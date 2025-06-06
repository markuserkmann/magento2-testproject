<?php

declare(strict_types=1);

namespace Laminas\View\Exception;

use DomainException as PhpDomainException;

/** @final */
class DomainException extends PhpDomainException implements ExceptionInterface
{
}
