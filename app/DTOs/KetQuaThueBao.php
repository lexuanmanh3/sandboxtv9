<?php

namespace App\DTOs;

final class KetQuaThueBao
{
    public function __construct(public string $soDienThoai, public ?string $nhaMang, public ?string $loaiThueBao, public array $duLieu = []) {}
}
