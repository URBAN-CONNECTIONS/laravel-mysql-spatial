<?php

namespace Grimzy\LaravelMysqlSpatial\Types;

use GeoIO\WKB\Parser\Parser;
use GeoJson\GeoJson;
use Grimzy\LaravelMysqlSpatial\Exceptions\UnknownSpatialFunctionException;
use Grimzy\LaravelMysqlSpatial\Exceptions\UnknownWKTTypeException;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;

abstract class Geometry implements GeometryInterface, Jsonable
{
    use Macroable;

    public const ST_RELATIONS = [
        'within',
        'crosses',
        'contains',
        'disjoint',
        'equals',
        'intersects',
        'overlaps',
        'touches',
    ];

    public const ST_ORDER_FUNCTIONS = [
        'distance',
        'distance_sphere',
    ];

    protected static $wkb_types = [
        1 => Point::class,
        2 => LineString::class,
        3 => Polygon::class,
        4 => MultiPoint::class,
        5 => MultiLineString::class,
        6 => MultiPolygon::class,
        7 => GeometryCollection::class,
    ];

    protected $srid;

    public function __construct($srid = 0)
    {
        $this->srid = (int) $srid;
    }

    public function getSrid()
    {
        return $this->srid;
    }

    public function setSrid($srid)
    {
        $this->srid = (int) $srid;
    }

    public static function getWKTArgument($value)
    {
        $left = strpos($value, '(');
        $right = strrpos($value, ')');

        return substr($value, $left + 1, $right - $left - 1);
    }

    public static function getWKTClass($value)
    {
        $left = strpos($value, '(');
        $type = trim(substr($value, 0, $left));

        switch (strtoupper($type)) {
            case 'POINT':
                return Point::class;
            case 'LINESTRING':
                return LineString::class;
            case 'POLYGON':
                return Polygon::class;
            case 'MULTIPOINT':
                return MultiPoint::class;
            case 'MULTILINESTRING':
                return MultiLineString::class;
            case 'MULTIPOLYGON':
                return MultiPolygon::class;
            case 'GEOMETRYCOLLECTION':
                return GeometryCollection::class;
            default:
                throw new UnknownWKTTypeException('Type was ' . $type);
        }
    }

    public static function fromWKB($wkb)
    {
        $srid = substr($wkb, 0, 4);
        $srid = unpack('L', $srid)[1];

        $wkb = substr($wkb, 4);
        $parser = new Parser(new Factory());

        /** @var Geometry $parsed */
        $parsed = $parser->parse($wkb);

        if ($srid > 0) {
            $parsed->setSrid($srid);
        }

        return $parsed;
    }

    public static function fromWKT(string $wkt, ?int $srid = null)
    {
        $wktArgument = static::getWKTArgument($wkt);

        return static::fromString($wktArgument, $srid);
    }

    public static function fromJson($geoJson)
    {
        if (is_string($geoJson)) {
            $geoJson = GeoJson::jsonUnserialize(json_decode($geoJson));
        }

        if ($geoJson->getType() === 'FeatureCollection') {
            return GeometryCollection::fromJson($geoJson);
        }

        if ($geoJson->getType() === 'Feature') {
            $geoJson = $geoJson->getGeometry();
        }

        $type = '\Grimzy\LaravelMysqlSpatial\Types\\' . $geoJson->getType();

        return $type::fromJson($geoJson);
    }

    public function toJson($options = 0)
    {
        return json_encode($this, $options);
    }

    public function distance(Geometry $other): float
    {
        return (float)$this->operation($other, 'distance');
    }

    public function sphereDistance(Geometry $other): float
    {
        return (float)$this->operation($other, 'distance_sphere');
    }

    public function within(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function crosses(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function contains(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function disjoint(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function equals(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function intersects(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function overlaps(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function touches(Geometry $other): bool
    {
        return (bool)$this->operation($other, __FUNCTION__);
    }

    public function operation(Geometry $other, string $operation): mixed
    {
        if (!in_array($operation, static::ST_ORDER_FUNCTIONS) && !in_array($operation, static::ST_RELATIONS)) {
            throw new UnknownSpatialFunctionException($operation);
        }

        $result = DB::select("select st_{$operation}(ST_GeomFromText(?, ?), ST_GeomFromText(?, ?)) as result", [
            $this->toWKT(),
            $this->getSrid(),
            $other->toWKT(),
            $other->getSrid(),
        ]);

        return $result[0]->result;
    }
}
