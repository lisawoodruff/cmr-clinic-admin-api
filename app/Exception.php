<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\RecordSignature;

class Exception extends Model
{

    use SoftDeletes;
    use RecordSignature;

    /**
     * fillable - attributes that can be mass-assigned
     */
    protected $fillable = [
            'id',
            'section',
            'name',
            'short_name',
            'attorney_note',
            'dyi_note',
            'logic',
            'sequence',
            'instructions',
            'deleted_at',
        ];

    protected $hidden = [
        'active',
        'created_by',
        'modified_by',
        'purged_by',
        'created_at',
        'updated_at',
    ];

    public function add($attributes)
    {

        try {
            $this->fill($attributes)->save();
        } catch (\Exception $e) {
            info(__METHOD__ . ' line: ' . __LINE__ . ':  ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            info(__METHOD__ . ' line: ' . __LINE__ . ':  ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        return true;
    }

    public function canDelete()
    {
        return false;
    }


    /**
     * Get Grid/index data PAGINATED
     *
     * @param $per_page
     * @param $column
     * @param $direction
     * @param string $keyword
     * @return mixed
     */
    static function indexData(
        $per_page,
        $column,
        $direction,
        $keyword = '')
    {
        return self::buildBaseGridQuery($column, $direction, $keyword,
            [ 'id',
                    'section',
                    'name',
                    'short_name',
                    'attorney_note',
                    'dyi_note',
                    'logic',
            ])
        ->paginate($per_page);
    }




    /**
     * Create base query to be used by Grid, Download, and PDF
     *
     * NOTE: to override the select you must supply all fields, ie you cannot add to the
     *       fields being selected.
     *
     * @param $column
     * @param $direction
     * @param string $keyword
     * @param string|array $columns
     * @return mixed
     */

    static function buildBaseGridQuery(
        $column,
        $direction,
        $keyword = '',
        $columns = '*')
    {
        // Map sort direction from 1/-1 integer to asc/desc sql keyword
        switch ($direction) {
            case '1':
                $direction = 'desc';
                break;
            case '-1':
                $direction = 'asc';
                break;
            default:
                $direction = 'asc';
                break;
        }

        $query = Exception::select($columns)
        ->orderBy($column, $direction);

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }
        return $query;
    }

        /**
         * Get export/Excel/download data query to send to Excel download library
         *
         * @param $per_page
         * @param $column
         * @param $direction
         * @param string $keyword
         * @return mixed
         */

    static function exportDataQuery(
        $column,
        $direction,
        $keyword = '',
        $columns = '*')
    {

        info(__METHOD__ . ' line: ' . __LINE__ . " $column, $direction, $keyword");

        return self::buildBaseGridQuery($column, $direction, $keyword, $columns);

    }

        static function pdfDataQuery(
            $column,
            $direction,
            $keyword = '',
            $columns = '*')
        {

            info(__METHOD__ . ' line: ' . __LINE__ . " $column, $direction, $keyword");

            return self::buildBaseGridQuery($column, $direction, $keyword, $columns);

        }


    /**
     * Get "options" for HTML select tag
     *
     * If flat return an array.
     * Otherwise, return an array of records.  Helps keep in proper order durring ajax calls to Chrome
     */
    static public function getOptions($flat = false)
    {

        $thisModel = new static;

        $records = $thisModel::select('id',
            'name','section','short_name')
            ->orderBy('sequence')
            ->get()
            ->map(function ($record, $key) {
                if (strlen($record->short_name) > 0) {
                    $record->name = $record->section . ' ' . $record->short_name;
                } else {
                    $record->name = $record->section . ' ' . $record->name;
                }


                unset($record->section);
                return $record;
            });

        if (!$flat) {
            return $records;
        } else {
            $data = [];

            foreach ($records AS $rec) {
                $data[] = ['id' => $rec['id'], 'name' => $rec['name']];
            }

            return $data;
        }

    }

}
