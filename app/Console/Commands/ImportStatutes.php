<?php

namespace App\Console\Commands;

use App\Exception;
use App\Jurisdiction;
use App\JurisdictionType;
use App\Statute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class ImportStatutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cmr:import-statutes {--file=statutes.yaml}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Statutes from yaml';
    private $jusrisdiction_types;
    private $jusrisdictions;
    /**
     * @var array|bool|string|null
     */
    private $file_name;
    private $exceptions;
    private $statuteIdIndex = [];
    /**
     * @var mixed
     */
    private $statutes;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {


        $this->jusrisdictions = Jurisdiction::with('type')->get()->flatMap(function ($j) {
            return [$this->makeKey($j) => $j];
        });
        $this->jusrisdiction_types = JurisdictionType::get()->flatMap(function ($j) {
            return [$this->makeKey($j) => $j];
        });
        $this->exceptions = Exception::get()->flatMap(function ($e) {
            return [$e->section => $e];
        });

        $this->file_name = $this->option('file');
        $this->statutes = Yaml::parseFile(base_path('/data/') . $this->file_name);

        // map previous statute id to current index in array
        foreach($this->statutes as $key => $statute) {
            $this->statuteIdIndex[$statute['id']] = $key;
        }

        DB::unprepared('set FOREIGN_KEY_CHECKS=0');

        $this->info("Statutes before " . Statute::count());
        dump("Statutes before " . Statute::count());
        foreach ($this->statutes as $statute) {

            print ".";
            if (!Statute::where('number', $statute['number'])->exists()) {
                print "+";
               $this->createStatute($statute);
            }

        }
        $this->info("Statutes after " . Statute::count());
        dump("Statutes after " . Statute::count());
        DB::unprepared('set FOREIGN_KEY_CHECKS=1');

        return 0;
    }

    private function createStatute($statute)
    {

        print("|" . $statute['number'] . "|\n");

        $jurisdiction = !empty($statute['jurisdiction']) ? $this->matchJurisdiction($statute['jurisdiction']) : null;

        if(!empty($statute['superseded_id'])
            && $statute['superseded_id'] != $statute['id']
            && ($supersededIndex = $this->findIndexById($statute['superseded_id']))
        ) {
            $supersededImport = $this->statutes[$supersededIndex];
            $superseded = Statute::where('number', $supersededImport['number'])->first();
            if(!$superseded) {
                $superseded = $this->createStatute($supersededImport);
            }
            $superseded_id = $superseded->id;
        }

        $record = Statute::create(
            [
                "number" => $statute['number'],
                "name" => $statute['name'],
                "note" => $statute['note'],
                "statutes_eligibility_id" => $statute['statutes_eligibility_id'],
                "superseded_id" => $superseded_id ?? null,
                "superseded_on" => $statute['superseded_on'],
                "deleted_at" => $statute['deleted_at'],
                "jurisdiction_id" => $jurisdiction->id ?? null,
                "same_as_id" => $statute['same_as_id'],
                "common_name" => $statute['common_name'],
                "blocks_time" => $statute['blocks_time'],
            ]
        );



        if(!empty($statute['exceptions'])) {
            $synced = [];
            foreach ($statute['exceptions'] as $exception) {
                $match = $this->matchException($exception);
                $synced [$match->id]= ['note' => $exception['pivot']['note'] ?? null];
            }
            $record->exceptions()->sync($synced);
        }

        return $record;
    }
    private function matchJurisdiction($jurisdiction)
    {
        if (isset($this->jusrisdictions[$this->makeKey($jurisdiction)])) {
            return $this->jusrisdictions[$this->makeKey($jurisdiction)];
        }

        // create jurisdiction
        return $this->createJurisdiction($jurisdiction);
    }

    private function makeKey($object)
    {

        if (is_array($object)) {
            return isset($object['type']) ? $object['name'] . '---' . $object['type']['name'] : $object['name'];
        }
        return isset($object->type) ? $object->name . '---' . $object->type->name : $object->name;
    }

    private function createJurisdiction($jurisdiction)
    {
        $type = $this->matchJurisdictionType($jurisdiction['type']);

        $data = [
            'jurisdiction_type_id' => $type->id,
            'name' => $jurisdiction['name'],
        ];
        $newJurisdiction = Jurisdiction::create($data);
        $this->jusrisdictions[$this->makeKey($newJurisdiction)] = $newJurisdiction;

        return $newJurisdiction;
    }

    private function matchJurisdictionType($type)
    {
        if (isset($this->jusrisdiction_types[$this->makeKey($type)])) {
            return $this->jusrisdiction_types[$this->makeKey($type)];
        }

        // create jurisdiction
        return $this->createJurisdictionType($type);
    }

    private function createJurisdictionType($type)
    {
        $data = [
            'name' => $type['name'],
        ];
        $newJurisdictionType = JurisdictionType::create($data);
        $this->jusrisdiction_types[$this->makeKey($newJurisdictionType)] = $newJurisdictionType;

        return $newJurisdictionType;
    }

    private function matchException($exception)
    {
        if (isset($this->exceptions[$exception['section']])) {
            return $this->exceptions[$exception['section']];
        }

        // create jurisdiction
        return $this->createException($exception);
    }

    private function createException($exception)
    {

        $data = [
            'name' => $exception['name'],
            'short_name' => $exception['short_name'],
            'section' => $exception['section'],
            'attorney_note' => $exception['attorney_note'],
            'dyi_note' => $exception['dyi_note'],
            'logic' => $exception['logic']
        ];

        $newException = Exception::create($data);
        $this->exceptions[$newException->section] = $newException;

        return $newException;
    }

    private function findIndexById($id)
    {
        return $this->statuteIdIndex[$id] ?? null;
    }
}
