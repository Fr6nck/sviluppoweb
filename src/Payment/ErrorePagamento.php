<?php

declare(strict_types=1);

namespace ArcoDelVento\Payment;

/** Qualcosa non è andato con SumUp. Il messaggio va nel registro, non all'ospite. */
final class ErrorePagamento extends \RuntimeException
{
}
