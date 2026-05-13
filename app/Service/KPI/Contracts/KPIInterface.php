<?php

namespace App\Service\KPI\Contracts;

interface KPIInterface
{
    public function summary(array $filters): array;
}
