<?php

namespace Grimzy\LaravelMysqlSpatial\Types;

use GeoJson\GeoJson;
use GeoJson\Geometry\Polygon as GeoJsonPolygon;
use Grimzy\LaravelMysqlSpatial\Exceptions\InvalidGeoJsonException;
use Grimzy\LaravelMysqlSpatial\Types\Concerns\PolygonFunctions;
use InvalidArgumentException;

class Polygon extends MultiLineString
{
    use PolygonFunctions;

    public function exteriorRing(): ?LineString
    {
        return $this->items[0] ?? null;
    }

    public function interiorRing(int $i = 0): ?LineString
    {
        if ($i < 0) {
            throw new InvalidArgumentException('Argument must be greater or equal than 0.');
        }

        return $this->items[$i + 1] ?? null;
    }

    public function interiorRingCount(): int
    {
        return max($this->count() - 1, 0);
    }

    public function toWKT(): string
    {
        return sprintf('POLYGON(%s)', (string) $this);
    }

    public static function fromJson($geoJson)
    {
        if (is_string($geoJson)) {
            $geoJson = GeoJson::jsonUnserialize(json_decode($geoJson));
        }

        if (!is_a($geoJson, GeoJsonPolygon::class)) {
            throw new InvalidGeoJsonException('Expected ' . GeoJsonPolygon::class . ', got ' . get_class($geoJson));
        }

        $set = [];
        foreach ($geoJson->getCoordinates() as $coordinates) {
            $points = [];
            foreach ($coordinates as $coordinate) {
                $points[] = new Point($coordinate[1], $coordinate[0]);
            }
            $set[] = new LineString($points);
        }

        return new self($set);
    }

    /**
     * Convert to GeoJson Polygon that is jsonable to GeoJSON.
     *
     * @return \GeoJson\Geometry\Polygon
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $linearRings = [];
        foreach ($this->items as $lineString) {
            $linearRings[] = new \GeoJson\Geometry\LinearRing($lineString->jsonSerialize()->getCoordinates());
        }

        return new GeoJsonPolygon($linearRings);
    }
}
