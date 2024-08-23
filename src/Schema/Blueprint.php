<?php

namespace Grimzy\LaravelMysqlSpatial\Schema;

use Illuminate\Database\Schema\Blueprint as IlluminateBlueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Fluent;

class Blueprint extends IlluminateBlueprint
{
    /** 
     * Add a point column on the table. 
     */
    public function point(string $column, int $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'point', $srid);
    }

    /** 
     * Add a line string column on the table. 
     */
    public function lineString(string $column, int $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'linestring', $srid);
    }

    /** 
     * Add a polygon column on the table. 
     */
    public function polygon(string $column, int $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'polygon', $srid);
    }

    /**
     * Add a multipoint column on the table.
     */
    public function multiPoint(string $column, int $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'multipoint', $srid);
    }

    /**
     * Add a multilinestring column on the table.
     */
    public function multiLineString(string $column, $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'multilinestring', $srid);
    }

    /**
     * Add a multipolygon column on the table.
     */
    public function multiPolygon(string $column, $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'multipolygon', $srid);
    }

    /**
     * Add a geometrycollection column on the table.
     */
    public function geometryCollection(string $column, $srid = 4326): ColumnDefinition
    {
        return $this->geography($column, 'geometrycollection', $srid);
    }

    /**
     * Specify a spatial index for the table.
     *
     * @param string|string[] $columns
     * @param string|null       $name
     *
     * @return \Illuminate\Support\Fluent
     */
    public function spatialIndex($columns, $name = null): Fluent
    {
        return $this->indexCommand('spatial', $columns, $name);
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param string|string[] $index
     *
     * @return \Illuminate\Support\Fluent
     */
    public function dropSpatialIndex($index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'spatial', $index);
    }
}
