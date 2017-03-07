<?php

namespace Lingxi\AliOpenSearch\Query;

use Illuminate\Support\Facades\Config;
use Lingxi\AliOpenSearch\Sdk\CloudsearchSearch;

/**
 * laravel eloquent builder scheme to opensearch scheme
 */
class Builder
{
    protected $cloudsearchSearch;

    public function __construct(CloudsearchSearch $cloudsearchSearch)
    {
        $this->cloudsearchSearch = $cloudsearchSearch;
    }

    public function build($builder)
    {
        $this->index($builder->index ?: $builder->model->searchableAs());
        $this->query($builder->query, $builder->rawQuerys);
        $this->filters($builder->wheres, $builder->rawWheres);
        $this->hit($builder->limit ?: 20);
        $this->sort($builder->orders);
        $this->addFields($builder->fields);

        $this->cloudsearchSearch->setFormat('json');

        return $this->cloudsearchSearch;
    }

    /**
     * 搜索的应用
     *
     * @param  array|string $index
     * @return null
     */
    protected function index($index)
    {
        if (is_array($index)) {
            foreach ($index as $key => $value) {
                $this->cloudsearchSearch->addIndex($value);
            }
        } else {
            $this->cloudsearchSearch->addIndex($index);
        }
    }

    /**
     * 过滤 filter 子句
     *
     * @see https://help.aliyun.com/document_detail/29158.html
     * @param  array $wheres
     * @return null
     */
    protected function filters(array $wheres, array $rawWheres)
    {
        foreach ($wheres as $key => $value) {
            $operator = $value[0];
            $value    = $value[1];
            if (!is_numeric($value) && is_string($value)) {
                // literal类型的字段值必须要加双引号，支持所有的关系运算，不支持算术运算
                $value = '"' . $value . '"';
            }

            $this->cloudsearchSearch->addFilter($key . $operator . $value, 'AND');
        }

        foreach ($rawWheres as $key => $value) {
            $this->cloudsearchSearch->addFilter($value, 'AND');
        }
    }

    /**
     * 查询 query 子句
     *
     * @example (name:'rry' AND age:'10') OR (name: 'lirui')
     *
     * @see https://help.aliyun.com/document_detail/29157.html
     * @param  mixed $query
     * @return null
     */
    protected function query($query, $rawQuerys)
    {
        if ($query instanceof QueryStructureBuilder) {
            $query = $query->toSql();
        } elseif (! is_string($query)) {
            $query = collect($query)
                ->map(function ($value, $key) {
                    return $key . ':\'' . $value . '\'';
                })
                ->implode(' AND ');
        }

        $query = $rawQuerys ? $query . ' AND ' . implode($rawQuerys, ' AND ') : $query;

        $this->cloudsearchSearch->setQueryString($query);
    }

    /**
     * 返回文档的最大数量
     *
     * @see https://help.aliyun.com/document_detail/29156.html
     * @param  integer $limit
     * @return null
     */
    protected function hit($limit)
    {
        $this->cloudsearchSearch->setHits($limit);
    }

    /**
     * 排序sort子句
     *
     * @see https://help.aliyun.com/document_detail/29159.html
     * @param  array $orders
     * @return null
     */
    protected function sort(array $orders)
    {
        foreach ($orders as $key => $value) {
            $this->cloudsearchSearch->addSort($value['column'], $value['column'] == 'asc' ? CloudsearchSearch::SORT_INCREASE : CloudsearchSearch::SORT_DECREASE);
        }
    }

    /**
     * 添加搜索字段
     *
     * @param   array  $fields
     * @return  null
     */
    protected function addFields(array $fields)
    {
        $this->cloudsearchSearch->addFetchFields($fields);
    }
}