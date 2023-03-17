<?php

namespace Grimzy\LaravelMysqlSpatial\Types;

interface GeometryInterface extends \JsonSerializable
{
    public function toWKT(): string;

    public static function fromWKT(string $wkt, ?int $srid = 0);

    public function __toString();

    public static function fromString(string $wktArgument, ?int $srid = 0);

    public static function fromJson($geoJson);
}
