<?php

namespace Grimzy\LaravelMysqlSpatial\Types\Concerns;

use Grimzy\LaravelMysqlSpatial\Types\LineString;
use Grimzy\LaravelMysqlSpatial\Types\Point;

trait PolygonFunctions
{
    public function area(): float
    {
        return (float)$this->operation(__FUNCTION__);
    }

    public function centroid(): ?Point
    {
        $area = $this->operation(__FUNCTION__);
        return $area ? Point::fromWKB($area) : $area;
    }
}
