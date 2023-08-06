<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Traits\ImageUpload;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class CRUDService
{
    use ImageUpload;

    protected $_model = null;

    /**
     * @var int
     */
    protected $_perPage = 10;

    protected $_hasImageField = false;

    public function setModel($model)
    {
        $this->_model = $model;
    }

    public function setPerPage($limit)
    {
        $this->_perPage = $limit;
    }

    public function setHasImageField($value)
    {
        $this->_hasImageField = $value;
    }
    
    public function search($attributes, $searchList = [], $orderBy = 'created_at', $order = 'desc', $needAll = false, $sortable = false, $requireQuery = false)
    {
        $query = $this->makeQuery($attributes, $searchList, $sortable);

        if ($sortable) {
            $items = $query->get();

            $sort = $attributes['sort'] ?? $orderBy;
            $direction = $attributes['direction'] ?? $order;

            if ($direction === 'desc') 
                $items = $items->sortByDesc($sort);
            else
                $items = $items->sortBy($sort);

            if ($needAll) {
                return $items;
            }
            
            return $this->paginate($items, $this->_perPage);
        } else {
            $query = $query->orderBy($orderBy, $order);
        }

        if ($requireQuery) return $query;

        if ($needAll) {
            return $query->get();
        }

        return $query->paginate($this->_perPage);
    }

    protected function equalQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where($key, $value);
        }

        return $query;
    }

    protected function likeQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where($key, "LIKE", "%{$value}%");
        }

        return $query;
    }

    protected function greaterQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where($key, '>=', $value);
        }

        return $query;
    }

    protected function lessQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where($key, '<=', $value);
        }

        return $query;
    }

    protected function inQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->whereIn($key, $value);
        }

        return $query;
    }

    protected function betweenQuery($query, $attributes, $key)
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $values = explode(",", $value);
            if ($values[0] !== '') $query->where($key, '>=', $values[0]);
            if ($values[1] !== '') $query->where($key, '<=', $values[1]);
        }

        return $query;
    }

    protected function freewordQuery($query, $attributes, $key, $columns = [])
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where(function ($query) use ($columns, $value) {
                foreach ($columns as $column) {
                    if ($column === 'student_name') {
                        $query->orWhereHas('student', function ($subquery) use ($value) {
                            $subquery->where(DB::raw("CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, ''))"), "LIKE", "%{$value}%");
                        });
                    } else {
                        $query->orWhere($column, "LIKE", "%{$value}%");
                    }
                }
            });
        }

        return $query;
    }

    protected function relationQuery($query, $attributes, $key, $columns = [])
    {
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->where(function ($query) use ($columns, $value) {
                foreach ($columns as $column) {
                    $query->orWhere($column, "LIKE", "%{$value}%");
                }
            });
        }

        return $query;
    }

    protected function relationHasQuery($query, $attributes, $key, $condition)
    {
        $field = str_replace("relation_has:", "", $condition);
        if (!is_null($value = Arr::get($attributes, $key))) {
            $query->whereHas($key, function ($query) use ($value, $field) {
                        $query->where($field, $value);        
                    });
        }

        return $query;
    }

    public function makeQuery($attributes, $searchList, $sortable)
    {
        if ($sortable) {
            $query = $this->_model->sortable();
        } else {
            $query = $this->_model->query();
        }

        foreach ($attributes as $key => $value) {
            if ($key === 'sort' || $key === 'direction' || $key === 'page') continue;

            $hasFiltered = false;
            foreach ($searchList as $subKey => $condition) {
                if ($subKey === $key) {
                    if ($condition === 'equal') {
                        $query = $this->equalQuery($query, $attributes, $key);
                    } elseif ($condition === 'like') {
                        $query = $this->likeQuery($query, $attributes, $key);
                    } elseif ($condition === 'gt') {
                        $query = $this->greaterQuery($query, $attributes, $key);
                    } elseif ($condition === 'lt') {
                        $query = $this->lessQuery($query, $attributes, $key);
                    } elseif ($condition === 'in') {
                        $query = $this->inQuery($query, $attributes, $key);
                    } elseif ($condition === 'between') {
                        $query = $this->betweenQuery($query, $attributes, $key);
                    } elseif (!is_array($condition) && substr($condition, 0, 12) === "relation_has") {
                        $query = $this->relationHasQuery($query, $attributes, $key, $condition);
                    } else {
                        $query = $this->freewordQuery($query, $attributes, $key, $condition);
                    }

                    $hasFiltered = true;
                }
            }

            if ($hasFiltered === false) {
                $query = $this->equalQuery($query, $attributes, $key);
            }
        }

        return $query;
    }

    public function create($attributes, $file = null)
    {
        if (!empty($value = Arr::get($attributes, 'password')) && !is_null($value = Arr::get($attributes, 'password'))) {
            $attributes['password'] = bcrypt($value);
        } else {
            unset($attributes['password']);
        }

        if (isset($file)) {
            $path = $this->imageUpdateWithThumb($this->_model::DIRECTORY, $file);
            $attributes[$this->_model::IMAGE_FIELD] = $path;
        }

        $item = $this->_model->create($attributes);

        return $item;
    }

    public function delete($id)
    {
        $item = $this->_model->findOrFail($id);
        if ($this->_hasImageField && isset($item->{$this->_model::IMAGE_FIELD})) {
            $this->deleteOriginFile($item->{$this->_model::IMAGE_FIELD});
        }
        $item->delete();
    }

    public function get($id)
    {
        return $this->_model->findOrFail($id);
    }

    public function update($id, $attributes, $file = null)
    {
        if (!empty($value = Arr::get($attributes, 'password')) && !is_null($value = Arr::get($attributes, 'password'))) {
            $attributes['password'] = bcrypt($value);
        } else {
            unset($attributes['password']);
        }
        
        $item = $this->_model->find($id);

        if (isset($file)) {
            if (isset($item->{$this->_model::IMAGE_FIELD})) {
                $this->deleteOriginFile($item->{$this->_model::IMAGE_FIELD});
            }
            $path = $this->imageUpdateWithThumb($this->_model::DIRECTORY, $file);
            $attributes[$this->_model::IMAGE_FIELD] = $path;
        }
        
        $item->update($attributes);

        return $this->_model->find($id);
    }

    public function paginate($items, $perPage = 10, $path = '', $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return (new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options))->withPath($path);
    }
}