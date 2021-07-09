<?php

namespace App\Models;

use App\Charge;
use App\Comment;
use App\History;
use App\Jurisdiction;
use App\StatuteException;
use App\StatutesEligibility;
use App\Traits\HistoryTrait;
use App\Traits\RecordSignature;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;

class LawVersion extends Model
{
//    use SoftDeletes;
    use RecordSignature;

//    use HistoryTrait;

    /**
     * fillable - attributes that can be mass-assigned.
     */
    protected $fillable = [
        'id',
        'law_id',
        'start_date',
        'end_date',
        'number',
        'name',
        'common_name',
        'jurisdiction_id',
        'note',
        'statutes_eligibility_id',
        'blocks_time',
        'same_as_id',
        'superseded_id',
        'superseded_on',
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

    public function superseded()
    {
        return $this->belongsTo(self::class);
    }

    public function jurisdiction()
    {
        return $this->belongsTo(Jurisdiction::class)->with('type');
    }

    public function charge()
    {
        return $this->hasMany(Charge::class);
    }

    public function law_version_exceptions()
    {
        return $this->hasMany(LawVersionException::class)->with('exception');
    }

    public function exceptions()
    {
        return $this->belongsToMany(\App\Exception::class, 'law_version_exceptions')->withPivot('note');
    }


    public function statutes_eligibility()
    {
        return $this->belongsTo(StatutesEligibility::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'comments');
    }

    /**
     * @return MorphMany
     */
    public function histories()
    {
        return $this->morphMany(History::class, 'historyable');
    }

    public function add($attributes)
    {
        try {
            $this->fill($attributes)->save();
        } catch (Exception $e) {
            info(__METHOD__ . ' line: ' . __LINE__ . ':  ' . $e->getMessage());
            throw new Exception($e->getMessage());
        } catch (QueryException $e) {
            info(__METHOD__ . ' line: ' . __LINE__ . ':  ' . $e->getMessage());
            throw new Exception($e->getMessage());
        }

        return true;
    }

    public function canDelete()
    {
        return true;
    }


    /**
     * Get Grid/index data PAGINATED.
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
            ['id',
                'number',
                'name',
            ])
            ->paginate($per_page);
    }


    /**
     * Create base query to be used by Grid, Download, and PDF.
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

        $query = self::select($columns)
            ->orderBy($column, $direction);

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }
        return $query;
    }

    /**
     * Get export/Excel/download data query to send to Excel download library.
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
            'name')
            ->orderBy('name')
            ->get();

        if (!$flat) {
            return $records;
        } else {
            $data = [];

            foreach ($records as $rec) {
                $data[] = ['id' => $rec['id'], 'name' => $rec['name']];
            }

            return $data;
        }

    }

    static public function baseFindQuery()
    {
        $query = self::with([
            'statutes_eligibility',
            'law_version_exceptions',
            'jurisdiction',
            'jurisdiction.type',
            'superseded' => function ($q) {
                $q->with('statutes_eligibility');
            },
            'histories' => function ($q) {
                $q->with('user')
                    ->orderBy('created_at', 'asc');
            }
        ])
            ->select('laws.id AS id',
                'law_versions.id AS law_version_id',
                'law_versions.start_date',
                'law_versions.end_date',
                'law_versions.number',
                'law_versions.name',
                'law_versions.common_name',
                'law_versions.jurisdiction_id',
                'law_versions.note',
                'law_versions.statutes_eligibility_id',
                'law_versions.blocks_time',
                'law_versions.same_as_id',
                'law_versions.superseded_id',
                'law_versions.superseded_on'
            )
            ->leftJoin('laws', 'laws.id', '=', 'law_versions.id');

        return $query;
    }


}