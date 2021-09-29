<?php

namespace App\Services;

class BaseService
{
    protected $model;

    public function create($params)
    {
        return $this->model->create($params);
    }

    public function update($id, $params)
    {
        $model = $this->model->find($id);
        $model->update($params);

        return $this->model->find($id);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id, $with = null)
    {
        $query = $this->model;

        if ($with) {
            $query = $query->with($with);
        }

        return $query->find($id);
    }

    public function deleteMore($ids)
    {
        return $this->model->destroy($ids);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function firstOrCreate($params)
    {
        return $this->model->firstOrCreate($params);
    }

    public function firstOrNew($params)
    {
        return $this->model->firstOrNew($params);
    }

    public function max($attribute)
    {
        return $this->model->max($attribute);
    }

    public function count(array $conditions = [])
    {
        $query = $this->model;

        if ($conditions) {
            $query = $query->where($conditions);
        }

        return $query->count();
    }

    public function insert($params)
    {
        return $this->model->insert($params);
    }

    public function all()
    {
        return $this->model->all();
    }
}
