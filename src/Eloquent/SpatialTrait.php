<?php

namespace Grimzy\LaravelMysqlSpatial\Eloquent;

use Grimzy\LaravelMysqlSpatial\Exceptions\SpatialFieldsNotDefinedException;
use Grimzy\LaravelMysqlSpatial\Exceptions\UnknownSpatialFunctionException;
use Grimzy\LaravelMysqlSpatial\Exceptions\UnknownSpatialRelationFunction;
use Grimzy\LaravelMysqlSpatial\Types\Geometry;
use Grimzy\LaravelMysqlSpatial\Types\GeometryInterface;
use Grimzy\LaravelMysqlSpatial\Types\Polygon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Trait SpatialTrait.
 *
 * @method static distance(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, float|int $distance)
 * @method static distanceExcludingSelf(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, float|int $distance)
 * @method static distanceSphere(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, float|int $distance)
 * @method static distanceSphereExcludingSelf(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, float|int $distance)
 * @method static comparison(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, string $relationship)
 * @method static within(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Polygon $polygon)
 * @method static crosses(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static contains(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static disjoint(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static equals(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static intersects(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static overlaps(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static doesTouch(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry)
 * @method static orderBySpatial(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, string $orderFunction, string $direction = 'asc')
 * @method static orderByDistance(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, string $direction = 'asc')
 * @method static orderByDistanceSphere(string $geometryColumn, \Grimzy\LaravelMysqlSpatial\Types\Geometry $geometry, string $direction = 'asc')
 */
trait SpatialTrait
{
    /*
     * The attributes that are spatial representations.
     * To use this Trait, add the following array to the model class
     *
     * @var array
     *
     * protected $spatialFields = [];
     */

    public $geometries = [];

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Grimzy\LaravelMysqlSpatial\Eloquent\Builder
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new BaseBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor()
        );
    }

    protected function performInsert(EloquentBuilder $query, array $options = [])
    {
        foreach ($this->attributes as $key => $value) {
            if ($value instanceof GeometryInterface) {
                $this->geometries[$key] = $value; //Preserve the geometry objects prior to the insert
                $this->attributes[$key] = new SpatialExpression($value);
            }
        }

        $insert = parent::performInsert($query, $options);

        foreach ($this->geometries as $key => $value) {
            $this->attributes[$key] = $value; //Retrieve the geometry objects so they can be used in the model
        }

        return $insert; //Return the result of the parent insert
    }

    public function setRawAttributes(array $attributes, $sync = false)
    {
        $spatial_fields = $this->getSpatialFields();

        foreach ($attributes as $attribute => &$value) {
            if (in_array($attribute, $spatial_fields) && is_string($value) && strlen($value) >= 13) {
                $value = Geometry::fromWKB($value);
            }
        }

        return parent::setRawAttributes($attributes, $sync);
    }

    public function getSpatialFields()
    {
        if (property_exists($this, 'spatialFields')) {
            return $this->spatialFields;
        } else {
            throw new SpatialFieldsNotDefinedException(__CLASS__ . ' has to define $spatialFields');
        }
    }

    public function isColumnAllowed(string $geometryColumn)
    {
        if (!in_array($geometryColumn, $this->getSpatialFields())) {
            throw new SpatialFieldsNotDefinedException();
        }

        return true;
    }

    public function scopeDistance(Builder $query, string $geometryColumn, Geometry $geometry, float|int $distance): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $query->whereRaw("st_distance(`$geometryColumn`, ST_GeomFromText(?, ?)) <= ?", [
            $geometry->toWkt(),
            $geometry->getSrid(),
            $distance,
        ]);

        return $query;
    }

    public function scopeDistanceExcludingSelf(Builder $query, string $geometryColumn, Geometry $geometry, float|int $distance): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $query = $this->scopeDistance($query, $geometryColumn, $geometry, $distance);

        $query->whereRaw("st_distance(`$geometryColumn`, ST_GeomFromText(?, ?)) != 0", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);

        return $query;
    }

    public function scopeDistanceValue(Builder $query, string $geometryColumn, Geometry $geometry, string $name = 'distance', bool $withPrefix = true): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $columns = $query->getQuery()->columns;

        if (!$columns) {
            $query->select('*');
        }

        if ($withPrefix) {
            $name = $geometryColumn . '_' . $name;
        }

        return $query->selectRaw("st_distance(`$geometryColumn`, ST_GeomFromText(?, ?)) as $name", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);
    }

    public function scopeDistanceSphere(Builder $query, string $geometryColumn, Geometry $geometry, float|int $distance): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $query->whereRaw("st_distance_sphere(`$geometryColumn`, ST_GeomFromText(?, ?)) <= ?", [
            $geometry->toWkt(),
            $geometry->getSrid(),
            $distance,
        ]);

        return $query;
    }

    public function scopeDistanceSphereExcludingSelf(Builder $query, string $geometryColumn, Geometry $geometry, float|int $distance): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $query = $this->scopeDistanceSphere($query, $geometryColumn, $geometry, $distance);

        $query->whereRaw("st_distance_sphere($geometryColumn, ST_GeomFromText(?, ?)) != 0", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);

        return $query;
    }

    public function scopeDistanceSphereValue(Builder $query, string $geometryColumn, Geometry $geometry, string $name = 'distance', bool $withPrefix = true): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        $columns = $query->getQuery()->columns;

        if (!$columns) {
            $query->select('*');
        }

        if ($withPrefix) {
            $name = $geometryColumn . '_' . $name;
        }

        return $query->selectRaw("st_distance_sphere(`$geometryColumn`, ST_GeomFromText(?, ?)) as $name", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);
    }

    public function scopeComparison(Builder $query, string $geometryColumn, Geometry $geometry, string $relationship): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        if (!in_array($relationship, Geometry::ST_RELATIONS)) {
            throw new UnknownSpatialRelationFunction($relationship);
        }

        $query->whereRaw("st_{$relationship}(`$geometryColumn`, ST_GeomFromText(?, ?))", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);

        return $query;
    }

    public function scopeWithin(Builder $query, string $geometryColumn, Polygon $polygon): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $polygon, 'within');
    }

    public function scopeCrosses(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'crosses');
    }

    public function scopeContains(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'contains');
    }

    public function scopeDisjoint(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'disjoint');
    }

    public function scopeEquals(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'equals');
    }

    public function scopeIntersects(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'intersects');
    }

    public function scopeOverlaps(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'overlaps');
    }

    public function scopeDoesTouch(Builder $query, string $geometryColumn, Geometry $geometry): Builder
    {
        return $this->scopeComparison($query, $geometryColumn, $geometry, 'touches');
    }

    public function scopeOrderBySpatial(Builder $query, string $geometryColumn, Geometry $geometry, string $orderFunction, string $direction = 'asc'): Builder
    {
        $this->isColumnAllowed($geometryColumn);

        if (!in_array($orderFunction, Geometry::ST_DISTANCE_FUNCTIONS)) {
            throw new UnknownSpatialFunctionException($orderFunction);
        }

        $query->orderByRaw("st_{$orderFunction}(`$geometryColumn`, ST_GeomFromText(?, ?)) {$direction}", [
            $geometry->toWkt(),
            $geometry->getSrid(),
        ]);

        return $query;
    }

    public function scopeOrderByDistance(Builder $query, string $geometryColumn, Geometry $geometry, string $direction = 'asc'): Builder
    {
        return $this->scopeOrderBySpatial($query, $geometryColumn, $geometry, 'distance', $direction);
    }

    public function scopeOrderByDistanceSphere(Builder $query, string $geometryColumn, Geometry $geometry, string $direction = 'asc'): Builder
    {
        return $this->scopeOrderBySpatial($query, $geometryColumn, $geometry, 'distance_sphere', $direction);
    }
}
