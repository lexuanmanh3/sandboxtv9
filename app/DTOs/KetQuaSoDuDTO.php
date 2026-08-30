<?php

namespace App\DTOs;

final class KetQuaSoDuDTO
{
    public function __construct(public string $soDu, public string $donVi = 'VND') {}
}
