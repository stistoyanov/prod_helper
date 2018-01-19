<?php

namespace Pulsio\Helpers;

// Models
use Pulsio\Order;
use Pulsio\ProductionElement;
use Pulsio\NewProductionOrder;
use Pulsio\OrderPreparerState;
use Pulsio\OrderPreparerElement;
use Pulsio\OrderPreparerRelation;
use Pulsio\NewProductionOrderArrange;
use Pulsio\NewProductionOrderProperty;
use Pulsio\NewProductionOrderReadyCopies;
use Pulsio\NewProductionOrderAdditionalProperty;

// Helpers
use Pulsio\Calculation\Machines;

// JOBs
use Pulsio\Jobs\NewProdOrderJob;

// Traits
use Pulsio\Helpers\ProductionTrait;
use Pulsio\Helpers\OrderProductionTrait;

// System
use DB;
use Log;
use Auth;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ProductionHelper
{
    use DispatchesJobs, ProductionTrait, OrderProductionTrait;

    protected $user;
    protected $language;
    protected $helperElementsActions;

    public function __construct()
    {
        $this->user = Auth::user();
        $this->language = config('app.locale');
    }

    /**
     * @return mixed
     */
    public function getHelperElementsActions()
    {
        return $this->helperElementsActions;
    }

    /**
     * @param mixed $helperElementsActions
     */
    public function setHelperElementsActions($helperElementsActions)
    {
        $this->helperElementsActions = $helperElementsActions;
    }

    public function getProductionElements()
    {
        $elements = [];

        $dbElements = DB::table('production_elements AS pe')
            ->join('production_element_translations AS pet_en', function ($join) {
                $join->on('pet_en.element_id', '=', 'pe.id');
                $join->on('pet_en.language', '=', DB::raw("'en'"));
            })
            ->join('production_element_translations AS pet_bg', function ($join) {
                $join->on('pet_bg.element_id', '=', 'pe.id');
                $join->on('pet_bg.language', '=', DB::raw("'bg'"));
            })
            ->join('production_element_translations AS pet_fr', function ($join) {
                $join->on('pet_fr.element_id', '=', 'pe.id');
                $join->on('pet_fr.language', '=', DB::raw("'fr'"));
            })
            ->select(
                'pe.id',
                'pe.slug',
                'pet_en.name AS name_en',
                'pet_bg.name AS name_bg',
                'pet_fr.name AS name_fr'
            )
            ->whereNotNull('slug')
            ->whereNull('pe.deleted_at')
            ->whereNull('pet_en.deleted_at')
            ->whereNull('pet_bg.deleted_at')
            ->whereNull('pet_fr.deleted_at')
            ->get();

        foreach ($dbElements as $element) {
            $elements[$element->id] = [
                'id' => $element->id,
                'slug' => $element->slug,
                'names' => ['en' => $element->name_en, 'bg' => $element->name_bg, 'fr' => $element->name_fr],
            ];
        }

        return $elements;
    }

    public function getEnabledSamples()
    {
        return [
            4, // Digital proof
            5, // Running sheets
        ];
    }

    public function getProductionSamples($isUsingDB = null)
    {
        $samples = [
            1 => ['id' => 1, 'hex' => 'CCCCCC', 'names' => ['bg' => 'Без', 'en' => 'Without', 'fr' => 'Sans']],
            2 => ['id' => 2, 'hex' => 'FFD700', 'names' => ['bg' => 'Проба от клиент', 'en' => 'Client sample', 'fr' => 'Fourni par le client']],
            3 => ['id' => 3, 'hex' => '4C6ECE', 'names' => ['bg' => 'Мостра', 'en' => 'Sample', 'fr' => 'Echantillon']],
            4 => ['id' => 4, 'hex' => '6DC174', 'names' => ['bg' => 'Дигитална Проба', 'en' => 'Digital proof', 'fr' => 'Chromalin']],
            5 => ['id' => 5, 'hex' => 'FFC0CB', 'names' => ['bg' => 'Реална Проба', 'en' => 'Running sheets', 'fr' => 'Essai réel']],
        ];

        if ($isUsingDB) {
            $dbItems = DB::table('order_samples AS os')
                ->join('order_sample_translations AS ost_en', function ($join) {
                    $join->on('ost_en.sample_id', '=', 'os.id');
                    $join->on('ost_en.language', '=', DB::raw("'en'"));
                })
                ->join('order_sample_translations AS ost_bg', function ($join) {
                    $join->on('ost_bg.sample_id', '=', 'os.id');
                    $join->on('ost_bg.language', '=', DB::raw("'bg'"));
                })
                ->join('order_sample_translations AS ost_fr', function ($join) {
                    $join->on('ost_fr.sample_id', '=', 'os.id');
                    $join->on('ost_fr.language', '=', DB::raw("'fr'"));
                })
                ->select(
                    'os.id',
                    'os.hex',
                    'ost_en.name AS name_en',
                    'ost_bg.name AS name_bg',
                    'ost_fr.name AS name_fr'
                )
                ->whereNull('os.deleted_at')
                ->whereNull('ost_en.deleted_at')
                ->whereNull('ost_bg.deleted_at')
                ->whereNull('ost_fr.deleted_at')
                ->get();

            if (count($dbItems) > 0) {
                foreach ($dbItems as $dbItem) {
                    $samples[$dbItem->id] = [
                        'id' => $dbItem->id,
                        'hex' => $dbItem->hex,
                        'names' => ['bg' => $dbItem->name_bg, 'en' => $dbItem->name_en, 'fr' => $dbItem->name_fr]
                    ];
                }
            }
        }

        return $samples;
    }

    public function getProductionSample($id, $isUsingDB = null)
    {
        return [
            'id' => $this->getProductionSamples($isUsingDB)[$id]['id'],
            'name' => $this->getProductionSamples($isUsingDB)[$id]['names'][$this->language],
        ];
    }

    public function getPreparerSampleStates()
    {
        return DB::table('order_sample_states AS oss')
            ->join('order_sample_state_translations AS osst', 'osst.state_id', '=', 'oss.id')
            ->select(
                'oss.id',
                'oss.hex',
                'osst.name',
                'oss.is_final',
                'oss.is_default'
            )
            ->whereNull('oss.deleted_at')
            ->whereNull('osst.deleted_at')
            ->where('osst.language', DB::raw("'{$this->language}'"))
            ->get();
    }

    public function getPreparerSampleStateName($id)
    {
        $name = 'ERROR';

        foreach ($this->getPreparerSampleStates() as $state) {
            if ($state->id == $id) {
                $name = $state->name;
            }
        }

        return $name;
    }

    public function getPreparerStates()
    {
        return DB::table('orders_preparers_states AS ops')
            ->join('orders_preparers_states_translations AS opst', 'opst.state_id', '=', 'ops.id')
            ->select(
                'ops.id',
                'ops.hex',
                'opst.name',
                'ops.is_final',
                'ops.is_default'
            )
            ->whereNull('ops.deleted_at')
            ->whereNull('opst.deleted_at')
            ->where('opst.language', DB::raw("'{$this->language}'"))
            ->get();
    }

    public function getPreparerDefaultStates($isUsingDB = null)
    {
        $states = [
            'state_id' => 1,
            'spine_id' => 1,
            'sample_id' => 1,
        ];

        if ($isUsingDB) {
            $preparerSpineState = OrderPreparerState::where('is_default', 1)
                ->select('id')
                ->first();

            $preparerOrderSampleState = DB::table('order_sample_states AS oss')
                ->join('order_sample_state_relations AS ossr', 'ossr.state_id', '=', 'oss.id')
                ->select('oss.id')
                ->where('oss.is_default', 1)
                ->whereIn('ossr.sample_id', $this->getEnabledSamples())
                ->whereNull('oss.deleted_at')
                ->whereNull('ossr.deleted_at')
                ->first();

            $states = [
                'state_id' => (isset($preparerSpineState)) ? $preparerSpineState->id : 1,
                'spine_id' => (isset($preparerSpineState)) ? $preparerSpineState->id : 1,
                'sample_id' => (isset($preparerOrderSampleState)) ? $preparerOrderSampleState->id : 1,
            ];
        }

        return $states;
    }

    public function getPreparerFinalStates($isUsingDB = null)
    {
        $states = [
            'state_id' => 4,
            'spine_id' => 4,
            'sample_id' => 2,
        ];

//        if ($isUsingDB) {
        $preparerSpineState = OrderPreparerState::where('is_final', 1)
            ->select('id')
            ->first();

        $preparerOrderSampleState = DB::table('order_sample_states AS oss')
            ->join('order_sample_state_relations AS ossr', 'ossr.state_id', '=', 'oss.id')
            ->select('oss.id')
            ->where('oss.is_final', 1)
            ->whereIn('ossr.sample_id', $this->getEnabledSamples())
            ->whereNull('oss.deleted_at')
            ->whereNull('ossr.deleted_at')
            ->first();

        $states = [
            'state_id' => (isset($preparerSpineState)) ? $preparerSpineState->id : 4,
            'spine_id' => (isset($preparerSpineState)) ? $preparerSpineState->id : 4,
            'sample_id' => (isset($preparerOrderSampleState)) ? $preparerOrderSampleState->id : 2,
        ];
//        }

        return $states;
    }

    public function orderHasSample($sampleId, $isUsingDB = null)
    {
        $result = 0;

        if (in_array($sampleId, $this->getEnabledSamples())) {
            $result = 1;
        }

        if ($isUsingDB) {
            $samples = DB::table('order_sample_state_relations AS ossr')
                ->select('ossr.sample_id')
                ->where('ossr.sample_id', $sampleId)
                ->whereNull('ossr.deleted_at')
                ->get();

            if (count($samples) > 0) {
                $result = 1;
            }
        }

        return $result;
    }

    public function getProductionSecondProductions($isUsingDB = null)
    {

        $productions = [
            1 => ['id' => 1, 'names' => ['bg' => 'Допечатка', 'en' => 'Допечатка', 'fr' => 'Допечатка']],
            2 => ['id' => 2, 'names' => ['bg' => 'Смяна на машина', 'en' => 'Смяна на машина', 'fr' => 'Смяна на машина']],
            3 => ['id' => 3, 'names' => ['bg' => 'Надраскана', 'en' => 'Надраскана', 'fr' => 'Надраскана']],
            4 => ['id' => 4, 'names' => ['bg' => 'Пасер', 'en' => 'Пасер', 'fr' => 'Пасер']],
            5 => ['id' => 5, 'names' => ['bg' => 'Нови файлове', 'en' => 'Нови файлове', 'fr' => 'Нови файлове']],
            6 => ['id' => 6, 'names' => ['bg' => 'Нов монтаж', 'en' => 'Нов монтаж', 'fr' => 'Нов монтаж']],
            7 => ['id' => 7, 'names' => ['bg' => 'Паднала', 'en' => 'Паднала', 'fr' => 'Паднала']],
            8 => ['id' => 8, 'names' => ['bg' => 'Свалена', 'en' => 'Свалена', 'fr' => 'Свалена']],
            9 => ['id' => 9, 'names' => ['bg' => 'Тонирала', 'en' => 'Тонирала', 'fr' => 'Тонирала']],
            10 => ['id' => 10, 'names' => ['bg' => 'Крива', 'en' => 'Крива', 'fr' => 'Крива']],
        ];

        if ($isUsingDB) {
            $dbItems = DB::table('production_second_productions AS psp')
                ->join('production_second_production_translations AS pspt_en', function ($join) {
                    $join->on('pspt_en.p_s_p_id', '=', 'psp.id');
                    $join->on('pspt_en.language', '=', DB::raw("'en'"));
                })
                ->join('production_second_production_translations AS pspt_bg', function ($join) {
                    $join->on('pspt_bg.p_s_p_id', '=', 'psp.id');
                    $join->on('pspt_bg.language', '=', DB::raw("'bg'"));
                })
                ->join('production_second_production_translations AS pspt_fr', function ($join) {
                    $join->on('pspt_fr.p_s_p_id', '=', 'psp.id');
                    $join->on('pspt_fr.language', '=', DB::raw("'fr'"));
                })
                ->select(
                    'psp.id',
                    'pspt_en.name AS name_en',
                    'pspt_bg.name AS name_bg',
                    'pspt_fr.name AS name_fr'
                )
                ->whereNull('psp.deleted_at')
                ->whereNull('pspt_en.deleted_at')
                ->whereNull('pspt_bg.deleted_at')
                ->whereNull('pspt_fr.deleted_at')
                ->get();

            if (count($dbItems) > 0) {
                foreach ($dbItems as $dbItem) {
                    $productions[$dbItem->id] = [
                        'id' => $dbItem->id,
                        'names' => ['bg' => $dbItem->name_bg, 'en' => $dbItem->name_en, 'fr' => $dbItem->name_fr]
                    ];
                }
            }
        }

        return $productions;
    }

    public function getProductionSecondProduction($id, $isUsingDB = null)
    {
        return [
            'id' => $this->getProductionSecondProductions($isUsingDB)[$id]['id'],
            'name' => $this->getProductionSecondProductions($isUsingDB)[$id]['names'][$this->language],
        ];
    }

    public static function getProductionElementBySlug($slug)
    {
        return DB::table('production_elements')
            ->select('id', 'slug')
            ->where('slug', $slug)
            ->whereNotNull('slug')
            ->whereNull('deleted_at')
            ->first();
    }

    public static function getProductionElementById($id)
    {
        return DB::table('production_elements')
            ->select('id', 'slug')
            ->where('id', $id)
            ->whereNotNull('slug')
            ->whereNull('deleted_at')
            ->first();
    }

    public static function convertibleOperatorTypes()
    {
        /*
         * order = 1 RED    == urgent
         * order = 2 YELLOW == is editing
         * order = 3 GRAY   == inactive
         * order = 4 GREEN  == done
         * */

        return [
            ['id' => 1, 'order' => 3], // 1 = Монтаж
            ['id' => 2, 'order' => 3], // 2 = Довършителен цех
            ['id' => 3, 'order' => 3], // 3 = Печатен цех
            ['id' => 4, 'order' => 3], // 4 = Технологичен цех
            ['id' => 5, 'order' => 3], // 5 = Склад
            ['id' => 6, 'order' => 3], // 6 = CTP
//            ['id' => 7, 'order' => 3], // 7 = Склад готова продукция ???
            ['id' => 8, 'order' => 1], // 8 = Препечат
        ];
    }

    public static function convertibleOperatorTypesForCopies()
    {
        /*
         * order = 1 RED    == urgent
         * order = 2 YELLOW == is editing
         * order = 3 GRAY   == inactive
         * order = 4 GREEN  == done
         * */

        return [
//            ['id' => 1, 'order' => 3], // 1 = Монтаж
            ['id' => 2, 'order' => 3], // 2 = Довършителен цех
//            ['id' => 3, 'order' => 3], // 3 = Печатен цех
//            ['id' => 4, 'order' => 3], // 4 = Технологичен цех
//            ['id' => 5, 'order' => 3], // 5 = Склад
//            ['id' => 6, 'order' => 3], // 6 = CTP
            ['id' => 7, 'order' => 3], // 7 = Склад готова продукция ???
//            ['id' => 8, 'order' => 1], // 8 = Препечат
        ];
    }

    public function operatorsInProduction($newProdOrderId)
    {
        return DB::table('new_production_order_arranges')
            ->select('opr_type_id AS id')
            ->where('prod_order_id', $newProdOrderId)
            ->where('order', '!=', 3)
            ->get();
    }

    public function getUser($id)
    {
        $data = [
            'id' => '0',
            'email' => env('MAIL_LOCAL_RECIPIENT'),
            'names' => 'SYSTEM',
        ];

        $user = DB::table('users')
            ->select(
                'users.id',
                'users.email',
                DB::raw("CONCAT_WS(' ',IFNULL(users.first_name,''),IFNULL(users.last_name,'')) AS names")
            )
            ->where('users.id', $id)
            ->first();

        if ($user) {
            $data = [
                'id' => $user->id,
                'email' => $user->email,
                'names' => $user->names,
            ];
        }

        return $data;
    }

    public function getUserByOprType($id)
    {
        $data = [
            0 => [
                'id' => '0',
                'operatorId' => '0',
                'email' => env('MAIL_LOCAL_RECIPIENT'),
                'names' => 'SYSTEM',
            ],
        ];

        $users = DB::table('users')
            ->join('production_operators AS po', 'po.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'po.id AS operatorId',
                'users.email',
                DB::raw("CONCAT_WS(' ',IFNULL(users.first_name,''),IFNULL(users.last_name,'')) AS names")
            )
            ->where('po.operator_type_id', $id)
            ->whereNull('po.deleted_at')
            ->whereNull('users.deleted_at')
            ->get();

        if (count($users) > 0) {
            unset($data);

            foreach ($users as $user) {
                $data[] = [
                    'id' => $user->id,
                    'operatorId' => $user->operatorId,
                    'email' => $user->email,
                    'names' => $user->names,
                ];
            }
        }

        return $data;
    }

    public function operatorTableNames()
    {
        return [
            1 => ['opr_type_id' => 1, 'enabled' => 1, 'name' => 'prepress'],
            2 => ['opr_type_id' => 2, 'enabled' => 1, 'name' => 'completion'],
            3 => ['opr_type_id' => 3, 'enabled' => 1, 'name' => 'fitter'],
            4 => ['opr_type_id' => 4, 'enabled' => 1, 'name' => 'technologist'],
            5 => ['opr_type_id' => 5, 'enabled' => 1, 'name' => 'workshop'],
            6 => ['opr_type_id' => 6, 'enabled' => 1, 'name' => 'c_t_p'],
            7 => ['opr_type_id' => 7, 'enabled' => 1, 'name' => 'w_r_p'],
            8 => ['opr_type_id' => 8, 'enabled' => 0, 'name' => 'preparer'],
        ];
    }

    public function getStatesPerOperatorType($oprTypeId)
    {
        if ($oprTypeId == 8) {
//            return $this->getPreparerStates();
            return $this->getPreparerSampleStates();
        }

        $roleTbl = null;
        foreach ($this->operatorTableNames() as $operatorTable) {
            if ($oprTypeId == $operatorTable['opr_type_id'] && $operatorTable['enabled'] == 1) {
                $roleTbl = $operatorTable['name'];
            }
        }

        if (!$roleTbl) {
            return [];
        }

        return DB::table("production_{$roleTbl}_action_states AS pas")
            ->join("production_{$roleTbl}_action_state_translations AS past", function ($join) {
                $join->on('past.state_id', '=', 'pas.id');
                $join->on('past.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'pas.id',
                'pas.hex',
                'past.name'
            )
            ->whereNull('pas.deleted_at')
            ->whereNull('past.deleted_at')
            ->get();
    }

    public function getActionsPerOperatorType($oprTypeId)
    {
        if ($oprTypeId == 8) {
            return json_decode(json_encode([
                0 => [
                    'id' => 1,
                    'name' => trans('production.done'),
                ],
            ]), false);
        }

        $roleTbl = null;
        foreach ($this->operatorTableNames() as $operatorTable) {
            if ($oprTypeId == $operatorTable['opr_type_id'] && $operatorTable['enabled'] == 1) {
                $roleTbl = $operatorTable['name'];
            }
        }

        if (!$roleTbl) {
            return [];
        }

        return DB::table("production_{$roleTbl}_actions AS pa")
            ->join("production_{$roleTbl}_action_translations AS pat", function ($join) {
                $join->on('pat.action_id', '=', 'pa.id');
                $join->on('pat.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'pa.id',
                'pat.name'
            )
            ->whereNull('pa.deleted_at')
            ->whereNull('pat.deleted_at')
            ->get();
    }

    public function getElementActionStateRelations($oprTypeId, $elementId)
    {
        if ($oprTypeId == 8) {
            $preparerFinalStateId = $this->getPreparerFinalStates()['state_id'];
            $preparerDefaultStateId = $this->getPreparerDefaultStates()['state_id'];
            return json_decode(json_encode([
                $preparerDefaultStateId => [
                    'id' => $preparerDefaultStateId,
                    'element_id' => $elementId,
                    'action_id' => 1,
                    'state_id' => $preparerDefaultStateId,
                    'is_default' => 1,
                    'is_final' => 0,
                ],
                $preparerFinalStateId => [
                    'id' => $preparerFinalStateId,
                    'element_id' => $elementId,
                    'action_id' => 1,
                    'state_id' => $preparerFinalStateId,
                    'is_default' => 0,
                    'is_final' => 1,
                ],
            ]), false);
        }

        $roleTbl = null;
        foreach ($this->operatorTableNames() as $operatorTable) {
            if ($oprTypeId == $operatorTable['opr_type_id'] && $operatorTable['enabled'] == 1) {
                $roleTbl = $operatorTable['name'];
            }
        }

        if (!$roleTbl) {
            return [];
        }

        return DB::table("production_{$roleTbl}_action_state_relations AS rel")
            ->select(
                'rel.id',
                'rel.element_id',
                'rel.action_id',
                'rel.state_id',
                'rel.is_default',
                'rel.is_final'
            )
            ->where('rel.element_id', $elementId)
            ->whereNull('rel.deleted_at')
            ->get();
    }

    public function getProdOrderLogsPerOperatorType($prodOrderId, $oprTypeId)
    {
        return DB::table('new_production_order_histories AS npoh')
            ->leftJoin('production_operators AS po', 'po.user_id', '=', 'npoh.user_id')
            ->select(
                'npoh.id',
                'npoh.text AS log',
                'po.id AS operatorId',
                'po.user_id AS userId',
                'po.name',
                DB::raw("DATE_FORMAT(npoh.created_at, '%d/%m/%Y %H:%i:%s') AS date")
            )
            ->where('npoh.prod_order_id', $prodOrderId)
            ->where('npoh.opr_type_id', $oprTypeId)
            ->orderBy('npoh.id', 'DESC')
            ->get();
    }

    public function datatablesBuilder($operatorTypeId)
    {
        return DB::table('new_production_order_arranges AS npoa')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'npoa.prod_order_id')
            ->join('new_production_order_properties AS npop', function ($join) {
                $join->on('npop.prod_order_id', '=', 'npo.id');
                $join->on('npop.opr_type_id', '=', DB::raw("'8'"));
                $join->on('npop.version', '=', DB::raw("'1'"));
            })
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->leftJoin('users AS traders', 'traders.id', '=', 'orders.created_by')
            ->leftJoin('production_operators AS po', 'po.id', '=', 'npo.technologist_id')
            ->leftJoin('users AS technologists', 'technologists.id', '=', 'po.user_id')
            ->leftJoin('users AS preparers', 'preparers.id', '=', 'npo.preparer_id')
            ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
            ->leftJoin('organizations', 'clients.organization_id', '=', 'organizations.id')
            ->join('products_translations AS pt', function ($join) {
                $join->on('pt.product_id', '=', 'orders.product_id');
                $join->on('pt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('product_properties_translations AS bt', function ($join) {
                $join->on('bt.property_id', '=', 'npo.binding_id');
                $join->on('bt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('product_properties_translations AS ct', function ($join) {
                $join->on('ct.property_id', '=', 'npo.color_id');
                $join->on('ct.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('production_machine_translations AS pmt', function ($join) {
                $join->on('pmt.machine_id', '=', 'npo.machine_id');
                $join->on('pmt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'npoa.order AS order',
                'npo.id AS productionOrderId',
                'npo.is_ready AS productionOrderIsReady',
                'npo.production_number AS productionOrderProductionNumber',
                'npo.distribution AS productionOrderDistribution',
                'npo.distribution_amount AS productionOrderDistributionAmount',
                'npo.distribution_bending AS productionOrderDistributionBending',
                'npo.machine_id AS productionOrderMachineId',
                'npo.big_sheets AS productionOrderBigSheets',
                'npo.pages AS productionOrderPages',
                'pmt.name AS productionOrderMachineName',
                'npo.real_spine',
                'npo.bleeds_inside',
                'npo.bleeds_outside',
                'npo.bleeds_legs',
                'npo.bleeds_head',
                'npo.preparer_id',
                'npo.binding_id',
                'npo.big_sheets',
                'npo.color_id AS color_id_text', // ??
                'npo.technologist_id',
                'npop.spine_state_id',
                'pt.name AS orderProductName',
                'bt.name AS productionOrderBindingName',
                'ct.name AS productionOrderColorName',
                'orders.id AS orderId',
                'orders.copies AS tirage',
                'orders.copies AS orderCopies',
                'orders.name AS title',
                'orders.name AS orderName',
                'orders.size_length AS orderFormatL',
                'orders.size_height AS orderFormatH',
                'orders.product_id AS orderProductId', // ??
                'orders.product_id',
                DB::raw("DATE_FORMAT(orders.due_date, '%d/%m/%Y') AS dueDate"),
                DB::raw("DATE_FORMAT(orders.due_date, '%d/%m/%Y') AS orderDueDate"), // ??
                DB::raw("DATE_FORMAT(orders.additional_date, '%d/%m/%Y') AS additionalDate"),
                DB::raw("DATE_FORMAT(orders.additional_date, '%d/%m/%Y') AS productionOrderAdditionalDate"), // ??
                DB::raw("DATE_FORMAT(npo.created_at, '%d/%m/%Y') AS assignedAt"),
                DB::raw("DATE_FORMAT(npo.updated_at, '%d/%m/%Y') AS updatedAt"),
                DB::raw("CONCAT_WS(' ',IFNULL(traders.first_name,''),IFNULL(traders.last_name,'')) AS traderNames"),
                DB::raw("IFNULL(traders.first_name,'') AS traderFirstName"), // ??
                DB::raw("IFNULL(traders.last_name,'') AS traderLastName"), // ??
                DB::raw("CONCAT_WS(' ',IFNULL(preparers.first_name,''),IFNULL(preparers.last_name,'')) AS preparerNames"),
                DB::raw("CONCAT_WS(' ',IFNULL(technologists.first_name,''),IFNULL(technologists.last_name,'')) AS technologistName"),
                DB::raw("CONCAT_WS(' ',IFNULL(clients.name,''),IFNULL(clients.last_name,'')) AS clientNames"),
                'organizations.name AS clientOrganizationName'
            )
            ->whereNull('orders.deleted_at')
            ->where('npo.version', 1)
            ->where('npoa.opr_type_id', $operatorTypeId)
            ->groupBy('orders.id')
            ->orderBy('npoa.order', 'ASC')
            ->orderBy('orders.due_date', 'ASC');
    }

    public function getProdOrderElementIdsPerOperatorType($prodOrderId, $oprTypeId)
    {
        return DB::table('new_production_order_additional_properties AS npoap')
            ->select(
                'npoap.id AS npoapId',
                'npoap.element_id AS id',
                'npoap.opr_type_id',
                'npoap.action_id',
                'npoap.state_id'
            )
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoap.prod_order_id', $prodOrderId)
            ->where('npoap.version', 1)
            ->get();
    }

    public function getProdOrderDistinctElementIdsPerOperatorType($prodOrderId, $oprTypeId)
    {
        return DB::table('new_production_order_additional_properties AS npoap')
            ->select(DB::raw('DISTINCT(npoap.element_id) AS id'))
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoap.prod_order_id', $prodOrderId)
            ->where('npoap.version', 1)
            ->get();
    }

    public function getStandByAndConvertedElementIds($prodOrderId, $fromOprTypeId, $toOprTypeId, $override = null)
    {
        if ($override) {
            $fromOprTypeId = $toOprTypeId;
        }

        $matchingElements = $this->getProdOrderElementIdsPerOperatorType($prodOrderId, $fromOprTypeId);
        $distinctElements = $this->getProdOrderDistinctElementIdsPerOperatorType($prodOrderId, $toOprTypeId);

        $standBy = [];
        $converted = [];
        foreach ($distinctElements as $distinctElement) {
            foreach ($matchingElements as $matchingElement) {
                if ($distinctElement->id == $matchingElement->id) {
                    $elementStates = $this->getElementActionStateRelations($fromOprTypeId, $distinctElement->id);
                    foreach ($elementStates as $elementState) {
                        if (
                            $matchingElement->id == $elementState->element_id
                            && $matchingElement->action_id == $elementState->action_id
                        ) {
                            if ($this->getHelperElementsActions() !== null) {
                                if (
                                    !empty($this->getHelperElementsActions()
                                    && in_array($matchingElement->action_id, $this->getHelperElementsActions()))
                                ) {
                                    if ($elementState->is_default < 1 && $elementState->is_final < 1) {
                                        if ($matchingElement->state_id == $elementState->state_id) {
                                            if ($override) {
                                                if ($elementState->is_final < 1) {
                                                    $converted[$distinctElement->id] = $distinctElement->id;
                                                }
                                            }
                                        } else {
                                            $standBy[$distinctElement->id] = $distinctElement->id;
                                        }
                                    } else {
                                        $criteria = $elementState->is_final;
                                        if ($override) {
                                            $criteria = $elementState->is_default;
                                        }
                                        if ($criteria > 0) {
                                            if ($matchingElement->state_id == $elementState->state_id) {
                                                $converted[$distinctElement->id] = $distinctElement->id;
                                            } else {
                                                $standBy[$distinctElement->id] = $distinctElement->id;
                                            }
                                        }
                                    }
                                }
                            } else {
                                if ($elementState->is_default < 1 && $elementState->is_final < 1) {
                                    if ($matchingElement->state_id == $elementState->state_id) {
                                        if ($override) {
                                            if ($elementState->is_final < 1) {
                                                $converted[$distinctElement->id] = $distinctElement->id;
                                            }
                                        }
                                    } else {
                                        $standBy[$distinctElement->id] = $distinctElement->id;
                                    }
                                } else {
                                    $criteria = $elementState->is_final;
                                    if ($override) {
                                        $criteria = $elementState->is_default;
                                    }
                                    if ($criteria > 0) {
                                        if ($matchingElement->state_id == $elementState->state_id) {
                                            $converted[$distinctElement->id] = $distinctElement->id;
                                        } else {
                                            $standBy[$distinctElement->id] = $distinctElement->id;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Fix matching "$standBy" elements from other operator type/
        // There might be more actions per element that we are checking
        if (!empty($standBy)) {
            foreach ($converted as $element => $elementId) {
                if (in_array($elementId, $standBy)) {
//                    unset($converted[$element]);
                    unset($standBy[$element]);
                }
            }
        }

        return [
            'standBy' => $standBy,
            'converted' => $converted,
        ];
    }

    public static function getOperatorId($userId)
    {
        $id = 0;

        $operator = DB::table('production_operators AS po')
            ->select('po.id')
            ->where('po.user_id', $userId)
            ->whereNull('po.deleted_at')
            ->first();

        if ($operator) {
            $id = $operator->id;
        }

        return $id;
    }

    public function getAllLaces()
    {
        return DB::table('laces AS l')
            ->join('laces_translations AS lt', function ($join) {
                $join->on('lt.lace_id', '=', 'l.id');
                $join->on('lt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'l.id',
                'l.code',
                'l.price',
                'l.thumbnail',
                'lt.name'
            )
            ->whereNull('l.deleted_at')
            ->whereNull('lt.deleted_at')
            ->get();
    }

    public function getLace($id)
    {
        $lace = [
            'id' => 0,
            'code' => 0,
            'price' => 0,
            'thumbnail' => '',
            'name' => '',
        ];

        foreach ($this->getAllLaces() as $dbLace) {
            if ($dbLace->id == $id) {
                unset($lace);
                $lace = $dbLace;
                break;
            }
        }

        return json_decode(json_encode($lace), false);
    }

    public function getAllHeadAndTailsBands()
    {
        return DB::table('head_and_tails_bands AS h')
            ->join('head_and_tails_bands_translations AS ht', function ($join) {
                $join->on('ht.head_and_tails_bands_id', '=', 'h.id');
                $join->on('ht.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'h.id',
                'h.code',
                'h.price',
                'h.thumbnail',
                'ht.name'
            )
            ->whereNull('h.deleted_at')
            ->whereNull('ht.deleted_at')
            ->get();
    }

    public function getHeadAndTailsBand($id)
    {
        $headAndTailsBand = [
            'id' => 0,
            'code' => 0,
            'price' => 0,
            'thumbnail' => '',
            'name' => '',
        ];

        foreach ($this->getAllHeadAndTailsBands() as $dbHeadAndTailsBand) {
            if ($dbHeadAndTailsBand->id == $id) {
                unset($headAndTailsBand);
                $headAndTailsBand = $dbHeadAndTailsBand;
                break;
            }
        }

        return json_decode(json_encode($headAndTailsBand), false);
    }

    public function getProdOrderProperties($prodOrderId, $oprTypeId, $elementId)
    {
        $results = [];

        $dbData = DB::table('new_production_order_properties AS npop')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'npop.prod_order_id')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('production_elements AS pe', 'pe.id', '=', DB::raw("'{$elementId}'"))
            ->join('production_element_translations AS pet', function ($join) {
                $join->on('pet.element_id', '=', 'pe.id');
                $join->on('pet.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'orders.sample_id',
                'orders.data',
                'npo.real_spine',
                'npop.pages',
                'npop.place',
                'npop.width',
                'npop.width_requested',
                'npop.big_sheets',
                'npop.number_of_sheets',
                'npop.paper_name',
                'npop.paper_name_id',
                'npop.paper_size_id',
                'npop.paper_color_id',
                'npop.paper_density_id',
                'npop.paper_supplier_id',
                'npop.paper_delivered',
                'npop.bookmark',
                'npop.bookmark_insert',
                'npop.color_id',
                'npop.machine_id',
                'npop.p_s_p_id',
                'npop.amount',
                'npop.has_sample',
                'npop.sample_state_id',
                'npop.sample_is_released',
                'npop.spine_state_id',
                'npop.remarks_spine',
                'npop.remarks_sample',
                'npop.remarks',
                'pe.slug AS elementSlug',
                'pet.name AS elementName',
                DB::raw("DATE_FORMAT(npop.created_at, '%d/%m/%Y') AS created_at"),
                DB::raw("DATE_FORMAT(npop.updated_at, '%d/%m/%Y') AS updated_at")
            )
            ->where('npop.prod_order_id', $prodOrderId)
            ->where('npop.opr_type_id', $oprTypeId)
            ->where('npop.element_id', $elementId)
            ->whereNull('pe.deleted_at')
            ->whereNull('pet.deleted_at')
            ->where('npop.version', 1)
            ->first();

        $preparerFinalStates = $this->getPreparerFinalStates();
        $preparerDefaultStates = $this->getPreparerDefaultStates();

        $paper_delivered = 0;
        $width_requested = 0;
        $tmpWorkshop = DB::table('new_production_order_properties')
            ->select('width_requested', 'paper_delivered')
            ->where('version', 1)
            ->where('prod_order_id', $prodOrderId)
            ->where('element_id', $elementId)
            ->where('opr_type_id', 5)// == Workshop
            ->first();
        if ($tmpWorkshop) {
//            $paper_delivered = $tmpWorkshop->paper_delivered;
            $paper_delivered = 1;
            $width_requested = $tmpWorkshop->width_requested;
        }

        if ($dbData) {
            $results['elementId'] = $elementId;
            $results['elementName'] = $dbData->elementName;
            $results['elementSlug'] = $dbData->elementSlug;
            $results['pages'] = $dbData->pages;
            $results['real_spine'] = $dbData->real_spine;
            $results['width'] = $dbData->width;
            $results['widthRequested'] = $dbData->width_requested;
            $results['place'] = $dbData->place;
            $results['place'] = $dbData->place;
            $results['bigSheets'] = $dbData->big_sheets;
            $results['numberOfSheets'] = $dbData->number_of_sheets;
            $results['paperNameId'] = $dbData->paper_name_id;
            $results['paperName'] = $this->getPaperName($dbData->paper_name_id);
            $results['paperNameCustom'] = $dbData->paper_name;
            $results['paperSizeId'] = $dbData->paper_size_id;
            $results['paperSizeName'] = $this->getPaperSize($dbData->paper_size_id);
            $results['paperColorId'] = $dbData->paper_color_id;
            $results['paperColorName'] = $this->getPaperColorName($dbData->paper_color_id);
            $results['paperDensityId'] = $dbData->paper_density_id;
            $results['paperDensityName'] = $this->getPaperDensity($dbData->paper_density_id);
            $results['paperSupplierId'] = $dbData->paper_supplier_id;
            $results['paperSupplierName'] = $this->getSupplier($dbData->paper_supplier_id);
            $results['paperDelivered'] = $dbData->paper_delivered;
            $results['bookmark'] = $dbData->bookmark;
            $results['bookmarkInsert'] = $dbData->bookmark_insert;
            $results['colorId'] = $dbData->color_id;
            $results['colorName'] = $this->getColorName($dbData->color_id); // ??
            $results['machineId'] = $dbData->machine_id;
            $results['machineName'] = $this->getMachineName($dbData->machine_id);
            $results['pspId'] = $dbData->p_s_p_id;
            $results['pspName'] = $this->getSecondProduction($dbData->p_s_p_id);
            $results['amount'] = $dbData->amount;
            $results['hasSample'] = $dbData->has_sample;
            $results['sampleId'] = $dbData->sample_id;
            $results['sampleName'] = $this->getSampleName($dbData->sample_id);
            $results['sampleStateId'] = $dbData->sample_state_id;
            $results['sampleStateName'] = $this->getPreparerSampleStateName($dbData->sample_state_id);
            $results['sampleDefault'] = $preparerDefaultStates['sample_id'];
            $results['sampleFinal'] = $preparerFinalStates['sample_id'];
            $results['sampleIsReleased'] = $dbData->sample_is_released;
            $results['spineStateId'] = $dbData->spine_state_id;
            $results['spineDefault'] = $preparerDefaultStates['spine_id'];
            $results['spineFinal'] = $preparerFinalStates['spine_id'];
            $results['remarksSpine'] = $dbData->remarks_spine;
            $results['remarksSample'] = $dbData->remarks_sample;
            $results['remarks'] = $dbData->remarks;
            $results['createdAt'] = $dbData->created_at;
            $results['updatedAt'] = $dbData->updated_at;
            $results['enable_width_workshop_edit']['paper_delivered'] = $paper_delivered;
            $results['enable_width_workshop_edit']['width_requested'] = $width_requested;
        }

        return $results;
    }

    public function getProdProperties($prodOrderId)
    {
        $results = [];

        $dbData = DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->select(
                'orders.id AS orderId',
                'orders.name AS orderName',
                'orders.data AS orderData',
                'npo.production_number',
                'npo.technologist_id',
                'npo.preparer_id',
                'npo.is_ready',
                'npo.copies_done',
                'npo.format_l',
                'npo.format_h',
                'npo.real_spine',
                'npo.bleeds_head',
                'npo.bleeds_legs',
                'npo.bleeds_inside',
                'npo.bleeds_outside',
                'npo.color_id',
                'npo.machine_id',
                'npo.binding_id',
                'npo.big_sheets',
                'npo.distribution',
                'npo.distribution_amount',
                'npo.distribution_bending',
                'npo.pages',
                'npo.remarks',
                DB::raw("DATE_FORMAT(npo.remarks_date, '%d/%m/%Y') AS remarks_date"),
                DB::raw("DATE_FORMAT(npo.created_at, '%d/%m/%Y') AS created_at"),
                DB::raw("DATE_FORMAT(npo.updated_at, '%d/%m/%Y') AS updated_at")
            )
            ->where('npo.id', $prodOrderId)
            ->where('npo.version', 1)
            ->first();

        if ($dbData) {

            $orderData = json_decode($dbData->orderData, true);

            $lace = 0;
            $laceName = trans('production.none');
            if (isset($orderData['lace_id']) && $orderData['lace_id'] > 0) {
                $dbLace = $this->getLace($orderData['lace_id']);
                $lace = $dbLace->id;
                $laceName = $dbLace->name;
            }
            $headAndTailsBand = 0;
            $headAndTailsBandName = trans('production.none');
            if (isset($orderData['head_and_tails_band_id']) && $orderData['head_and_tails_band_id'] > 0) {
                $dbHeadAndTailsBand = $this->getHeadAndTailsBand($orderData['head_and_tails_band_id']);
                $headAndTailsBand = $dbHeadAndTailsBand->id;
                $headAndTailsBandName = $dbHeadAndTailsBand->name;
            }
            $bookmark = 0; // няма разделител
            $bookmarkInsert = 0; // 1 : 0 == с влагане : без влагане
            $bookmarkInsertName = trans('production.none');
            if (isset($orderData['bookmark'])) {
                $bookmark = 1; // има разделител
                if (isset($orderData['bookmark_insert'])) {
                    $bookmarkInsert = $orderData['bookmark_insert'];
                    $bookmarkInsertName = trans('production.withoutBookmark');
                    if ($bookmarkInsert > 0) {
                        $bookmarkInsertName = trans('production.withBookmark');
                    }
                }
            }
            $foilPacked = 0;
            $foilPackedName = trans('texts.no');
            if (isset($orderData['foil_packed']) && $orderData['foil_packed'] > 0) {
                $foilPacked = 1;
                $foilPackedName = trans('texts.yes');
            }
            $foilWrapped = 0;
            $foilWrappedName = trans('texts.no');
            if (isset($orderData['foil_wrapped']) && $orderData['foil_wrapped'] > 0) {
                $foilWrapped = 1;
                $foilWrappedName = trans('texts.yes');
            }

            $results['productionNumber'] = $dbData->production_number;
            $results['technologistId'] = $dbData->technologist_id;
            $results['preparerId'] = $dbData->preparer_id;
            $results['isReady'] = $dbData->is_ready;
            $results['copiesDone'] = $dbData->copies_done;
            $results['formatL'] = $dbData->format_l;
            $results['formatH'] = $dbData->format_h;
            $results['realSpine'] = $dbData->real_spine;
            $results['bleedsHead'] = $dbData->bleeds_head;
            $results['bleedsLegs'] = $dbData->bleeds_legs;
            $results['bleedsInside'] = $dbData->bleeds_inside;
            $results['bleedsOutside'] = $dbData->bleeds_outside;
            $results['colorId'] = $dbData->color_id;
            $results['colorName'] = $this->getColorName($dbData->color_id);
            $results['machineId'] = $dbData->machine_id;
            $results['machineName'] = $this->getMachineName($dbData->machine_id);
            $results['bindingId'] = $dbData->binding_id;
            $results['bindingName'] = $this->getBindingName($dbData->binding_id);
            $results['bigSheets'] = $dbData->big_sheets;
            $results['distribution'] = $dbData->distribution;
            $results['distributionName'] = $this->getDistributionName($dbData->distribution);
            $results['distributionAmount'] = $dbData->distribution_amount;
            $results['distributionBinding'] = $dbData->distribution_bending;
            $results['distributionBindingName'] = $this->getBending($dbData->distribution_bending)->name;
            $results['pages'] = $dbData->pages;
            $results['remarks'] = $dbData->remarks;
            $results['remarksDate'] = $dbData->remarks_date;
            $results['createdAt'] = $dbData->created_at;
            $results['createdAt'] = $dbData->updated_at;
            $results['foilPacked'] = $foilPacked;
            $results['foilPackedName'] = $foilPackedName;
            $results['foilWrapped'] = $foilWrapped;
            $results['foilWrappedName'] = $foilWrappedName;
            $results['lace'] = $lace;
            $results['laceName'] = $laceName;
            $results['headAndTailsBand'] = $headAndTailsBand;
            $results['headAndTailsBandName'] = $headAndTailsBandName;
            $results['bookmark'] = $bookmark;
            $results['bookmarkInsert'] = $bookmarkInsert;
            $results['bookmarkInsertName'] = $bookmarkInsertName;
        }

        return $results;
    }

    public function getProdOrderAdditionalPropertyCurrentState($prodOrderId, $oprTypeId, $elementId, $actionId)
    {
        return DB::table('new_production_order_additional_properties AS npoap')
            ->select(
                'npoap.element_id',
                'npoap.action_id',
                'npoap.state_id AS id',
                'npoap.remarks'
            )
            ->where('npoap.prod_order_id', $prodOrderId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoap.element_id', $elementId)
            ->where('npoap.action_id', $actionId)
            ->where('npoap.version', 1)
            ->first();
    }

    public function getProdOrderAdditionalPropertyUniState($oprTypeId, $elementId, $actionId)
    {
        $state = null;

        $convertedStates = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($convertedStates as $convertedState) {
            if ($actionId == $convertedState->action_id && $convertedState->is_default == 0 && $convertedState->is_final == 0) {
                $state = $convertedState->state_id;
            }
        }

        return $state;
    }

    public function getProdOrderAdditionalPropertyUniStates($oprTypeId, $elementId, $actionId)
    {
        $states = [];

        $convertedStates = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($convertedStates as $convertedState) {
            if ($actionId == $convertedState->action_id && $convertedState->is_default == 0 && $convertedState->is_final == 0) {
                $states[] = $convertedState->state_id;
            }
        }

        return $states;
    }

    public function getProdOrderAdditionalPropertyDefaultState($oprTypeId, $elementId, $actionId)
    {
        $state = null;

        $convertedStates = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($convertedStates as $convertedState) {
            if ($actionId == $convertedState->action_id && $convertedState->is_default > 0) {
                $state = $convertedState->state_id;
            }
        }

        return $state;
    }

    public function getProdOrderAdditionalPropertyFinalState($oprTypeId, $elementId, $actionId)
    {
        $state = null;

        $convertedStates = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($convertedStates as $convertedState) {
            if ($actionId == $convertedState->action_id && $convertedState->is_final > 0) {
                $state = $convertedState->state_id;
            }
        }

        return $state;
    }

    public function getProdOrderAdditionalPropertyFinalStates($oprTypeId, $elementId, $actionId)
    {
        $states = [];

        $convertedStates = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($convertedStates as $convertedState) {
            if ($actionId == $convertedState->action_id && $convertedState->is_final > 0) {
                $states[] = $convertedState->state_id;
            }
        }

        return $states;
    }

    public function getStandByAndConvertedActionStates($prodOrderId, $oprTypeId, $elementId, $helperElements)
    {
        $results = [];

        $states = $this->getStatesPerOperatorType($oprTypeId);
        if (count($states) < 1) {
            return trans('production.noStates');
        }
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        if (count($actions) < 1) {
            return trans('production.noActions');
        }

        $results['orderProperties'] = $this->getProdProperties($prodOrderId);
        $results['properties'] = $this->getProdOrderProperties($prodOrderId, $oprTypeId, $elementId);

        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);

        $results['actions'] = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $relationId = 0;
                $stateIsFinal = 0;
                $stateIsDefault = 0;
                $stateIsChecked = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $relationId = $relation->id;
                                $stateIsFinal = $relation->is_final;
                                $stateIsDefault = $relation->is_default;
                            }
                        }
                    }
                }

                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isFinal' => $stateIsFinal,
                        'isDefault' => $stateIsDefault,
                        'relationId' => $relationId,
                    ];
                }
            }

            // Add only assigned actions
            if ($actionIsChecked > 0) {
                $selectedState = 0;
                $selectedStateRemarks = '';
                $currentState = $this->getProdOrderAdditionalPropertyCurrentState($prodOrderId, $oprTypeId, $elementId, $action->id);

                if ($currentState) {
                    $selectedState = $currentState->id;
                    $selectedStateRemarks = $currentState->remarks;
                }

                $stateFinal = 0;
                $finalState = $this->getProdOrderAdditionalPropertyFinalState($oprTypeId, $elementId, $action->id);
                if ($finalState) {
                    $stateFinal = $finalState;
                }

                $actionsMapped[$action->id] = [
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'checked' => $actionIsChecked,
                    'stateFinal' => $stateFinal,
                    'selectedState' => $selectedState,
                    'selectedStateRemarks' => $selectedStateRemarks,
                ];

                if (in_array($elementId, $helperElements['converted'])) {
                    $results['actions']['active'] = $actionsMapped;
                } elseif (in_array($elementId, $helperElements['standBy'])) {
                    $results['actions']['standBy'] = $actionsMapped;
                }
            }
        }

        return $results;
    }

    public function convertOrderToProductionOnStore(Order $order)
    {
        if (env('BYPASS_PRODUCTION') < 1) {
            $order->save();
            // Translate orders only if printing_id == 1 === Pulsio
            if ($order->printing_id == 1) {
                $newProdOrder = NewProductionOrder::where('order_id', $order->id)
                    ->first();
                if (!$newProdOrder) {
                    $this->orderTranslatorToProductionOrder($order->id);
                }
            }
            // turn state to "order"
            $order->order_state_id = 4;
            $order->save();
        }

        return true;
    }

    public function deleteProductionOrderToPreparer($id)
    {
        $order = Order::find($id);
        $userId = ($this->user->id) ? $this->user->id : 0;
        $newProdOrder = NewProductionOrder::where('order_id', $id)
            ->where('version', 1)
            ->first();

        if (!$order || !$newProdOrder) {
            return [];
        }

        $dbOrder = $order;
        $operator = $this->mapOperator();
        $toOprTypeId = 4; // Technologist
        $date = Carbon::now()->toDateTimeString();

        $newProdOrderId = $newProdOrder->id;

        $fitterArrange = NewProductionOrderArrange::where('prod_order_id', $newProdOrderId)
            ->where('opr_type_id', 3)// 3 == Fitter
//                    ->where('order', '=', 1)  // RED
//                    ->where('order', '=', 2)  // YELLOW
            ->where('order', '!=', 3)// Inactive
//                    ->where('order', '=', 4)  // GREEN
            ->first();

        if ($fitterArrange) {
            return redirect()->back()->withErrors([0 => trans('texts.wrongId')]);
        }

        $logRevert = "Поръчката е върната";

        $previous = [
//            4, // Technologist
        ];
        $convertibles = [
            4, // Technologist
            5, // Workshop
            1, // Prepress
            6, // CTP
            3, // Fitter
            2, // Completion
        ];

        $convertiblesAndOperator = array_merge($convertibles, [$toOprTypeId]);

        $arranges = NewProductionOrderArrange::where('prod_order_id', $newProdOrderId)
            ->whereIn('opr_type_id', $convertiblesAndOperator)
            ->get();

        if (count($arranges) > 0) {
            foreach ($arranges as $arrange) {
                $order = 3; // Inactive
                if (in_array($arrange->opr_type_id, $previous)) {
                    $order = 1; // RED
                }
                $arrange->order = $order;
                $arrange->save();
            }
        }

        $latestVersionNPOP = 2;
        $newProdOrderProperties = NewProductionOrderProperty::where('prod_order_id', $newProdOrderId)
            ->where('opr_type_id', $toOprTypeId)
            ->orderBy('version', 'DESC')
            ->first();

        if ($newProdOrderProperties) {
            $latestVersionNPOP = ++$newProdOrderProperties->version;
        }

        $latestVersionNPOAP = 2;
        $newProdOrderAdditionalProperties = NewProductionOrderAdditionalProperty::where('prod_order_id', $newProdOrderId)
            ->where('opr_type_id', $toOprTypeId)
            ->orderBy('version', 'DESC')
            ->first();
        if ($newProdOrderAdditionalProperties) {
            $latestVersionNPOAP = ++$newProdOrderAdditionalProperties->version;
        }

        $poapsToConvert = [];
        foreach ($convertibles as $oprTypeId) {
            $poapsToConvert[] = $this->getCurrentDataOnStore($newProdOrderId, $oprTypeId);
        }

        if (!empty($poapsToConvert)) {
            foreach ($poapsToConvert as $poaps) {
                foreach ($poaps as $poap) {
                    DB::table('new_production_order_properties')
                        ->where('id', $poap->npopId)->update([
                            'version' => $latestVersionNPOP,
                            'opr_id' => $operator['id'],
                            'updated_at' => $date,
                        ]);
                    DB::table('new_production_order_additional_properties')
                        ->where('id', $poap->npoapId)->update([
                            'version' => $latestVersionNPOAP,
                            'opr_id' => $operator['id'],
                            'updated_at' => $date,
                        ]);
                }
            }
        }

        $newProdOrderCompletionReadyCopies = NewProductionOrderReadyCopies::where('prod_order_id', $newProdOrderId)
            ->where('opr_type_id', 2)// Completion
            ->orderBy('version', 'DESC')
            ->first();
        if ($newProdOrderCompletionReadyCopies) {
            $latestVersionCompletionRC = ++$newProdOrderCompletionReadyCopies->version;
            $newProdOrderCompletionReadyCopies->version = $latestVersionCompletionRC;
            $newProdOrderCompletionReadyCopies->save();
        }

        $newProdOrderWRPReadyCopies = NewProductionOrderReadyCopies::where('prod_order_id', $newProdOrderId)
            ->where('opr_type_id', 7)// WRP
            ->orderBy('version', 'DESC')
            ->first();
        if ($newProdOrderWRPReadyCopies) {
            $latestVersionWRPRC = ++$newProdOrderWRPReadyCopies->version;
            $newProdOrderWRPReadyCopies->version = $latestVersionWRPRC;
            $newProdOrderWRPReadyCopies->save();
        }

        // EMAIL Notifications

        $from = $this->getUser($userId);
        $tos = [];
        foreach ($convertibles as $oprTypeId) {
            $this->revertOrderTranslatorToProductionOrder($dbOrder->id, $oprTypeId);

            $operators = $this->getUserByOprType($oprTypeId);
            foreach ($operators as $operator) {
                $tos[] = $operator;
            }
        }

        $subject = "ID: {$dbOrder->id} / Заглавие: {$dbOrder->name} - върната";
        $preparerData['logs'][] = $logRevert;
        $preparerData['from'] = $from;
        $preparerData['title'] = $subject;
        $preparerData['subject'] = $subject;
        foreach ($tos as $to) {
            $preparerData['to'] = $to;
        }

        // END EMAIL Notifications

        return $preparerData;
    }

    public function deleteProductionOrder($id)
    {
        $order = Order::find($id);
        $newProdOrder = NewProductionOrder::where('order_id', $id)
            ->where('version', 1)
            ->first();

        if (!$order || !$newProdOrder) {
            return [];
        }

        $newProdOrderId = $newProdOrder->id;

        $operatorsInProduction = $this->operatorsInProduction($newProdOrderId);

        $tos = [];
        if (count($operatorsInProduction) > 0) {
            foreach ($operatorsInProduction as $operatorType) {
                $operators = $this->getUserByOprType($operatorType->id);
                foreach ($operators as $operator) {
                    $tos[] = $operator;
                }

            }
        }

        \DB::beginTransaction();

        try {

            DB::table('new_production_orders')->where('order_id', $id)->delete();
            DB::table('new_production_order_arranges')->where('prod_order_id', $newProdOrderId)->delete();
            DB::table('new_production_order_histories')->where('prod_order_id', $newProdOrderId)->delete();
            DB::table('new_production_order_properties')->where('prod_order_id', $newProdOrderId)->delete();
            DB::table('new_production_order_ready_copies')->where('prod_order_id', $newProdOrderId)->delete();
            DB::table('new_production_order_additional_properties')->where('prod_order_id', $newProdOrderId)->delete();

        } catch (\Exception $e) {

            \Log::notice($e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            \DB::rollBack();

            return redirect()->back()->withErrors(trans('texts.wrongId'));

        }

        \DB::commit();

        $preparerData = [];

        if (!empty($tos)) {
            $from = $this->getUser($order->created_by);
            $subject = "ID: {$order->id} / Заглавие: {$order->name} - ИЗТРИТА";
            $log = "Поръчка с ID: {$order->id} / Заглавие {$order->name}, е изтрита от (търговец): {$from['names']}";

            foreach ($tos as $to) {
                $preparerData[] = [
                    'from' => $from,
                    'to' => $to,
                    'title' => $subject,
                    'subject' => $subject,
                    'logs' => [0 => $log],
                ];
            }
        }

        return $preparerData;
    }

    public static function orderPropertiesDelete($id)
    {
        \DB::beginTransaction();

        try {

            DB::table('order_properties')->where('order_id', $id)->delete();

        } catch (\Exception $e) {

            \Log::notice($e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            \DB::rollBack();

            return redirect()->back()->withErrors(trans('texts.wrongId'));

        }

        \DB::commit();

        return true;
    }

    public static function orderPropertiesInsert(Order $order)
    {
        $propertiesData = [];

        DB::table('order_properties')
            ->where('order_id', $order->id)
            ->delete();

        $elements = ProductionElement::select('id', 'slug')
            ->whereNotNull('slug')
            ->get();

        foreach ($elements as $element) {

            if (isset($order->data[$element->slug]['pages']) && $order->data[$element->slug]['pages'] > 0) {
                $paper_id = 0;
                // Additional check for "jacket" && "banderole"
                if (
                    $element->id == 4    // == Обложка/jacket
                    || $element->id == 5 // == Бандерол/banderole
                ) {
                    $pNameId = (isset($order->data[$element->slug]['paper']))
                        ? $order->data[$element->slug]['paper'] : 0; // paper == paper_name_id
                    $pColorId = (isset($order->data[$element->slug]['paper_color']))
                        ? $order->data[$element->slug]['paper_color'] : 0; // paper_color == paper_color_id
                    $pDensityId = (isset($order->data[$element->slug]['paper_density']))
                        ? $order->data[$element->slug]['paper_density'] : 0; // paper_density == paper_density_id
                    $paper = DB::table('papers AS p')
                        ->join('products_papers_relation AS ppr', function ($join) use ($order, $element) {
                            $join->on('ppr.paper_id', '=', 'p.id');
                            $join->on('ppr.product_id', '=', DB::raw("'{$order->product_id}'"));
                            $join->on('ppr.component', '=', DB::raw("'{$element->slug}'"));
                        })
                        ->select('p.id')
                        ->where('p.paper_name_id', $pNameId)
                        ->where('p.paper_color_id', $pColorId)
                        ->where('p.paper_density_id', $pDensityId)
                        ->where("p.has_{$element->slug}", 1)
                        ->where('p.enabled', 1)
                        ->whereNull('p.deleted_at')
                        ->first();
                    if ($paper) {
                        $paper_id = $paper->id;
                    }

                } else {

                    if (isset($order->data['additional_data']['distributionParts'][$element->slug])) {
                        $paper_id = isset($order->data['additional_data']["{$element->slug}_paper_id"])
                            ? $order->data['additional_data']["{$element->slug}_paper_id"] : 0;
                    }

                }

                $propertiesData[$element->id] = [
                    'paper_id' => $paper_id,
                    'order_id' => $order->id,
                    'element_id' => $element->id,
                    'pages' => $order->data[$element->slug]['pages'],
                ];
            }
        }

        if (count($propertiesData) > 0) {

            try {

                DB::table('order_properties')->insert($propertiesData);

            } catch (\Exception $e) {

                Log::notice($e->getMessage(), ['code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            }
        }

        return true;
    }

    public function revertProdOrderProperties($newProdOrderId, $oprTypeId)
    {

        $npops = DB::table('new_production_order_properties AS npop')
            ->select('npop.id', 'npop.version')
            ->where('npop.prod_order_id', $newProdOrderId)
            ->get();

        $npoaps = DB::table('new_production_order_additional_properties AS npoap')
            ->select('npoap.id', 'npoap.version')
            ->where('npoap.prod_order_id', $newProdOrderId)
            ->get();

        if (count($npops) < 1 || count($npoaps) < 1) {
            return [];
        }

        foreach ($npops as $npop) {
            DB::table('new_production_order_properties')
                ->where('id', $npop->id)
                ->update(['version' => ++$npop->version]);
        }

        foreach ($npoaps as $npoap) {
            DB::table('new_production_order_additional_properties')
                ->where('id', $npoap->id)
                ->update(['version' => ++$npoap->version]);
        }

        $tos = [];
        $operators = $this->getUserByOprType($oprTypeId);
        foreach ($operators as $operator) {
            $tos[] = $operator;
        }

        return $tos;
    }

    public function orderProductionState($orderId)
    {
        $result = 0;

        $order = DB::table('orders')
            ->join('new_production_orders AS npo', 'npo.order_id', '=', 'orders.id')
            ->join('new_production_order_properties AS npop', 'npop.prod_order_id', '=', 'npo.id')
            ->join('new_production_order_arranges AS npoa', function ($join) {
                $join->on('npoa.prod_order_id', '=', 'npo.id');
                $join->on('npoa.opr_type_id', '=', DB::raw("'3'")); // 3 = Fitter
            })
            ->select(
                'orders.additional_date',
                'npo.id',
                'npop.spine_state_id'
            )
            ->where('orders.id', $orderId)
            ->where('orders.order_state_id', '<', 6)
            ->where('orders.printing_id', 1)// Pulsio printing
            ->where('npop.opr_type_id', 8)// Preparer
            ->where('npop.element_id', 2)// Text
//            ->where('npoa.order', '=', 3)// 3 = Inactive
            ->first();

        if (!$order) {
            // Show btn for "Send to Prepaper"
            return 1;

        } else {
            // Show btn "Revert from Preparer"
            $result = 2;
        }

        $finalState = $this->getPreparerFinalStates()['state_id'];

        if ($finalState == $order->spine_state_id) {
            // Show "Send to Production"
            $result = 3;
        }

        // $result = 4 == show btn "Revert from Production"
        $checkOperators = NewProductionOrderArrange::where('prod_order_id', $order->id)
            ->get();
        if (count($checkOperators) > 0) {
            foreach ($checkOperators as $operator) {
                // Check Technologist == 4
                if ($operator->opr_type_id == 4) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                } // Check Workshop == 5
                elseif ($operator->opr_type_id == 5) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                } // Check Prepress == 1
                elseif ($operator->opr_type_id == 1) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                } // Check CTP == 6
                elseif ($operator->opr_type_id == 6) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                } // Check Fitter == 3
                elseif ($operator->opr_type_id == 3) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                } // Check Completion == 2
                elseif ($operator->opr_type_id == 2) {
                    if ($operator->order != 3) {
                        $result = 4;
                    }
                }
            }
        }

        return $result;
    }

    public function prodOrderData($newProdOrderId)
    {
        return DB::table('new_production_order_arranges AS npoa')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'npoa.prod_order_id')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->join('products_translations AS pt', function ($join) {
                $join->on('pt.product_id', '=', 'orders.product_id');
                $join->on('pt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'orders.id AS orderId',
                'orders.name AS orderName',
                'orders.product_id',
                'orders.data AS orderData',
                'orders.sample_id AS orderSampleId',
                'orders.product_id AS orderProductId',
                'orders.additional_date_remarks AS productionOrderAdditionalDateRemarks',
                'pt.name AS orderProductName',
                'npo.id AS newProdOrderId',
                'npo.format_l AS productionOrderFormatL',
                'npo.format_h AS productionOrderFormatH',
                'npo.id AS productionOrderId',
                'npo.format_l AS productionOrderFormatL',
                'npo.format_h AS productionOrderFormatH',
                'npo.bleeds_inside AS productionOrderBleedInside',
                'npo.bleeds_outside AS productionOrderBleedOutside',
                'npo.bleeds_legs AS productionOrderBleedLegs',
                'npo.bleeds_head AS productionOrderBleedHead',
                'npo.real_spine AS productionOrderRealSpine',
                'npo.technologist_id AS productionOrderTechnologistId',
                'npo.distribution AS productionOrderDistribution',
                'npo.distribution_amount AS productionOrderDistributionAmount',
                'npo.distribution_Bending AS productionOrderDistributionBending',
                'npo.binding_id AS productionOrderBindingId',
                'npo.color_id AS productionOrderColorId',
                'npo.machine_id AS productionOrderMachineId',
                'npo.copies_done AS productionOrderCopiesDone',
                'npo.pages AS productionOrderPages',
                'npo.big_sheets AS productionOrderBigSheets',
                'npo.number_of_sheets AS productionOrderNumberOfSheetsText',
                'npo.production_number AS productionOrderProductionNumber',
                'npo.remarks AS productionOrderRemarks',
                DB::raw("DATE_FORMAT(npo.remarks_date, '%d/%m/%Y') AS productionOrderRemarksDate"),
                DB::raw("DATE_FORMAT(npo.updated_at, '%d/%m/%Y') AS productionOrderUpdatedAt"),
                'npo.is_ready AS productionOrderIsReady'
            )
            ->where('npoa.prod_order_id', $newProdOrderId)
            ->where('npoa.opr_type_id', 4)// == Technologist
            ->where('npo.version', 1)
            ->where('products.enabled', 1)
            ->whereNull('orders.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNull('pt.deleted_at');
    }

    public function orderDataPreparer($orderId, $elementId)
    {
        return DB::table('new_production_order_arranges AS npoa')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'npoa.prod_order_id')
            ->join('new_production_order_properties AS npop', 'npop.prod_order_id', '=', 'npoa.prod_order_id')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('order_samples AS os', 'os.id', '=', 'orders.sample_id')
            ->join('order_sample_translations AS ost', function ($join) {
                $join->on('ost.sample_id', '=', 'os.id');
                $join->on('ost.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('order_sample_states AS oss', 'oss.id', '=', 'npop.sample_state_id')
            ->leftJoin('order_sample_state_translations AS osst', function ($join) {
                $join->on('osst.state_id', '=', 'oss.id');
                $join->on('osst.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('order_sample_states AS ossr', 'ossr.id', '=', 'npop.sample_is_released')
            ->leftJoin('order_sample_state_translations AS ossrt', function ($join) {
                $join->on('ossrt.state_id', '=', 'ossr.id');
                $join->on('ossrt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->join('orders_preparers_states AS ops', 'ops.id', '=', 'npop.spine_state_id')
            ->join('orders_preparers_states_translations AS opst', function ($join) {
                $join->on('opst.state_id', '=', 'ops.id');
                $join->on('opst.language', '=', DB::raw("'{$this->language}'"));
            })
            ->leftJoin('users', 'users.id', '=', 'npo.preparer_id')
            ->select(
                'os.id AS sampleId',
                'os.hex AS sampleStateHex',
                'ost.name AS sampleName',
                'oss.hex AS sampleStateHex',
                'osst.name AS sampleStateName',
                'ossr.id AS sampleStateIsReleasedId',
                'ossr.hex AS sampleStateIsReleasedHex',
                'ossrt.name AS sampleStateIsReleasedName',
                'ops.id AS spineStateId',
                'ops.hex AS spineStateHex',
                'ops.is_final AS spineStateIsFinal',
                'opst.name AS spineStateName',
                'npop.element_id AS elementId',
                'npop.spine_state_id AS spineStateId',
                'npop.sample_state_id AS sampleStateId',
                'npop.remarks_sample AS sampleStateRemarks',
                'users.id AS preparerId',
                DB::raw("CONCAT_WS(' ',IFNULL(users.first_name,''),IFNULL(users.last_name,'')) AS preparerNames")
            )
            ->where('npop.element_id', $elementId)
            ->where('npo.order_id', $orderId)
            ->where('npoa.opr_type_id', 8)// == Preparer
            ->where('npop.opr_type_id', 8)// == Preparer
            ->where('npo.version', 1)
            ->where('npop.version', 1)
            ->whereNull('os.deleted_at')
            ->whereNull('ost.deleted_at')
            ->whereNull('ops.deleted_at')
            ->whereNull('opst.deleted_at')
            ->whereNull('oss.deleted_at')
            ->whereNull('ossr.deleted_at')
            ->whereNull('ossr.deleted_at')
            ->whereNull('ossrt.deleted_at')
            ->first();
    }

    public function getNewProdOrderAdditionalPropertyIds($newProdOrderId, $elementId, $oprTypeId)
    {
        return DB::table('new_production_order_additional_properties AS npoap')
            ->select('npoap.id')
            ->where('npoap.prod_order_id', $newProdOrderId)
            ->where('npoap.element_id', $elementId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoap.version', 1)
            ->get();
    }

    public function getNewProdOrderPropertiesPerElement($newProdOrderId, $elementId, $oprTypeId)
    {
        return DB::table('new_production_order_properties AS npop')
            ->select(
                'npop.color_id',
                'npop.machine_id',
                'npop.paper_name',
                'npop.paper_name_id',
                'npop.paper_size_id',
                'npop.paper_color_id',
                'npop.paper_density_id',
                'npop.paper_supplier_id',
                'npop.pages',
                'npop.big_sheets',
                'npop.number_of_sheets',
                'npop.bookmark',
                'npop.bookmark_insert',
                'npop.p_s_p_id',
                'npop.amount',
                'npop.has_sample',
                'npop.sample_state_id',
                'npop.sample_is_released',
                'npop.spine_state_id',
                'npop.remarks',
                'npop.remarks_spine',
                'npop.remarks_sample'
            )
            ->where('npop.prod_order_id', $newProdOrderId)
            ->where('npop.element_id', $elementId)
            ->where('npop.opr_type_id', $oprTypeId)
            ->where('npop.version', 1)
            ->first();
    }

    public function getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $actionId, $oprTypeId)
    {
        return DB::table('new_production_order_additional_properties AS npoap')
            ->select('npoap.state_id')
            ->where('npoap.prod_order_id', $newProdOrderId)
            ->where('npoap.element_id', $elementId)
            ->where('npoap.action_id', $actionId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoap.version', 1)
            ->first();
    }

    public function getNewProdOrderAdditionalPropertyFinalStateId($elementId, $actionId, $oprTypeId)
    {
        $result = null;

        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        foreach ($relations as $relation) {
            if (
                1 == $relation->is_final
                && $elementId == $relation->element_id
                && $actionId == $relation->action_id
            ) {
                $result = $relation->state_id;
            }
        }

        return $result;
    }

    public function orderDataTechnologist($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 4;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                // handle $enable
                $enable = 0;
                $actionIdPreparer = 1;
                $oprTypeIdPreparer = 8;
                $newProdOrderPropertiesPreparer = $this->getNewProdOrderPropertiesPerElement($newProdOrderId, $elementId, $oprTypeIdPreparer);
                if ($newProdOrderPropertiesPreparer) {
                    $finalStateIdPreparer = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $actionIdPreparer, $oprTypeIdPreparer);
                    if ($newProdOrderPropertiesPreparer->spine_state_id == $finalStateIdPreparer) {
                        $enable = $finalStateIdPreparer;
                    }
                }

                $bookmark = 0;
                $paperSizeId = 0;
                $currentPages = 0;
                $currentColorId = 0;
                $paperDensityId = 0;
                $bookmarkInsert = 0;
                $currentBigSheets = 0;
                $currentMachineId = 0;
                $currentNumberOfSheets = 0;
                $newProdOrderProperties = $this->getNewProdOrderPropertiesPerElement($newProdOrderId, $elementId, $oprTypeId);
                if ($newProdOrderProperties) {
                    $bookmark = $newProdOrderProperties->bookmark;
                    $currentPages = $newProdOrderProperties->pages;
                    $currentColorId = $newProdOrderProperties->color_id;
                    $paperSizeId = $newProdOrderProperties->paper_size_id;
                    $currentBigSheets = $newProdOrderProperties->big_sheets;
                    $currentMachineId = $newProdOrderProperties->machine_id;
                    $bookmarkInsert = $newProdOrderProperties->bookmark_insert;
                    $paperDensityId = $newProdOrderProperties->paper_density_id;
                    $currentNumberOfSheets = $newProdOrderProperties->number_of_sheets;
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'enable' => (($action->id == 2) && ($enable < 1)) ? 1 : $enable, // Always enabled for action 2
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                    'pages' => $currentPages,
                    'colorId' => $currentColorId,
                    'bookmark' => $bookmark,
                    'paperSizeId' => $paperSizeId,
                    'bigSheets' => $currentBigSheets,
                    'machineId' => $currentMachineId,
                    'bookmarkInsert' => $bookmarkInsert,
                    'paperDensityId' => $paperDensityId,
                    'numberOfSheets' => $currentNumberOfSheets,
                ];
                $data['actions'] = $actionsMapped;
                // Count Bookmarks
                $countBookmarkInserts = 0;
                foreach ($actionsMapped as $actionMapped) {
                    if ($actionMapped['bookmarkInsert'] > 0) {
                        $countBookmarkInserts++;
                    }
                }
                $data['countBookmarkInserts'] = $countBookmarkInserts;
            }
        }

        return $data;
    }

    public function orderDataWorkshop($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 5;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                $hasPaper = 0;
                $paperNameCustom = null;
                $paperName = '';
                $paperColor = '';
                $paperFormat = '';
                $paperDensity = '';
                $newProdOrderProperties = $this->getNewProdOrderPropertiesPerElement($newProdOrderId, $elementId, $oprTypeId);
                if ($newProdOrderProperties) {
                    $paperNameCustom = (
                        isset($newProdOrderProperties->paper_name)
                        && !empty($newProdOrderProperties->paper_name)
                        && $newProdOrderProperties->paper_name != ' ')
                        ? $newProdOrderProperties->paper_name : null;
                    $pName = DB::table('papers_names_translations')
                        ->select('name')
                        ->where('paper_name_id', $newProdOrderProperties->paper_name_id)
                        ->where('language', DB::raw("'{$this->language}'"))
                        ->whereNull('deleted_at')
                        ->first();
                    if ($pName) {
                        $paperName = $pName->name;
                    }
                    $pColor = DB::table('papers_colors_translations')
                        ->select('name')
                        ->where('paper_color_id', $newProdOrderProperties->paper_color_id)
                        ->where('language', DB::raw("'{$this->language}'"))
                        ->whereNull('deleted_at')
                        ->first();
                    if ($pColor) {
                        $paperColor = $pColor->name;
                    }
                    $pDensity = DB::table('papers_densities')
                        ->select('density')
                        ->where('id', $newProdOrderProperties->paper_density_id)
                        ->whereNull('deleted_at')
                        ->first();
                    if ($pDensity) {
                        $paperDensity = $pDensity->density;
                    }
                    $pFormat = DB::table('papers_sizes')
                        ->select('height', 'length')
                        ->where('id', $newProdOrderProperties->paper_size_id)
                        ->whereNull('deleted_at')
                        ->first();
                    if ($pFormat) {
                        $height = round($pFormat->height, 0);
                        $length = round($pFormat->length, 0);
                        $paperFormat = "{$height}/{$length}";
                    }
                    if ($pName && $pColor && $pDensity && $pFormat) {
                        $hasPaper = 1;
                    }
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                    'paperNameCustom' => $paperNameCustom,
                    'paperName' => $paperName,
                    'paperFormat' => $paperFormat,
                    'paperDensity' => $paperDensity,
                    'paperColor' => $paperColor,
                    'hasPaper' => $hasPaper,
                ];
                $data['actions'] = $actionsMapped;
            }
        }

        return $data;
    }

    public function orderDataPrepress($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 1;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                ];
                $data['actions'] = $actionsMapped;
            }
        }

        return $data;
    }

    public function orderDataCTP($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 6;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                ];
                $data['actions'] = $actionsMapped;
            }
        }

        return $data;
    }

    public function orderDataFitter($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 3;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                ];
                $data['actions'] = $actionsMapped;
            }
        }

        return $data;
    }

    public function getReadyCopies($newProdOrderId, $oprTypeId)
    {
        $result = 0;

        $data = DB::table('new_production_order_ready_copies AS nporc')
            ->select('nporc.copies'/*, 'nporc.order', 'nporc.is_ready'*/)
            ->where('nporc.prod_order_id', $newProdOrderId)
            ->where('nporc.operator_id', $oprTypeId)
            ->first();

        if ($data) {
            $result = $data->copies;
        }

        return $result;
    }

    public function orderDataCompletion($orderId, $newProdOrderId, $elementId)
    {
        $data = [];
        $oprTypeId = 2;
        $relations = $this->getElementActionStateRelations($oprTypeId, $elementId);
        $states = $this->getStatesPerOperatorType($oprTypeId);
        $actions = $this->getActionsPerOperatorType($oprTypeId);
        $actionsMapped = [];
        foreach ($actions as $action) {
            $statesMapped = [];
            $actionIsChecked = 0;
            foreach ($states as $state) {
                $stateIsChecked = 0;
                $stateIsDefault = 0;
                $stateIsFinal = 0;
                $relationId = 0;
                if (count($relations) > 0) {
                    foreach ($relations as $relation) {
                        if ($action->id == $relation->action_id) {
                            $actionIsChecked = 1;
                            if ($state->id == $relation->state_id) {
                                $stateIsChecked = 1;
                                $stateIsDefault = $relation->is_default;
                                $stateIsFinal = $relation->is_final;
                                $relationId = $relation->id;
                            }
                        }
                    }
                }
                // Add only assigned states
                if ($stateIsChecked > 0) {
                    $statesMapped[$state->id] = [
                        'id' => $state->id,
                        'hex' => $state->hex,
                        'name' => $state->name,
                        'checked' => $stateIsChecked,
                        'isDefault' => $stateIsDefault,
                        'isFinal' => $stateIsFinal,
                        'relationId' => $relationId,
                    ];
                }
            }
            // Add only assigned actions
            if ($actionIsChecked > 0) {

                $currentState = 0;
                $newProdOrderCurrentStateId = $this->getNewProdOrderAdditionalPropertyCurrentStateId($newProdOrderId, $elementId, $action->id, $oprTypeId);
                if ($newProdOrderCurrentStateId) {
                    $currentState = $newProdOrderCurrentStateId->state_id;
                }

                $isFinalState = 0;
                $finalStateId = $this->getNewProdOrderAdditionalPropertyFinalStateId($elementId, $action->id, $oprTypeId);
                if ($finalStateId) {
                    if ($finalStateId == $currentState) {
                        $isFinalState = $finalStateId; // > 0
                    }
                }

                $actionsMapped[$action->id] = [
                    'checked' => $actionIsChecked,
                    'id' => $action->id,
                    'name' => $action->name,
                    'states' => $statesMapped,
                    'currentState' => $currentState,
                    'isFinalState' => $isFinalState,
                ];
                $data['actions'] = $actionsMapped;
            }
        }

        return $data;
    }

    public function oldGetProdLoadsNoFlaps($currentDays)
    {
        return DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('production_loads AS plBS', 'plBS.production_order_id', '=', 'npo.id')
            ->join('production_loads AS plNOST', 'plNOST.production_order_id', '=', 'npo.id')
            ->leftJoin('new_production_order_properties AS npoapText', function ($join) {
                $join->on('npoapText.prod_order_id', '=', 'npo.id');
                $join->on('npoapText.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapText.element_id', '=', DB::raw("'2'"));  // == Text
                $join->on('npoapText.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapCover', function ($join) {
                $join->on('npoapCover.prod_order_id', '=', 'npo.id');
                $join->on('npoapCover.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapCover.element_id', '=', DB::raw("'1'"));  // == Cover
                $join->on('npoapCover.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapInsert', function ($join) {
                $join->on('npoapInsert.prod_order_id', '=', 'npo.id');
                $join->on('npoapInsert.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapInsert.element_id', '=', DB::raw("'3'"));  // == Insert
                $join->on('npoapInsert.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapJacket', function ($join) {
                $join->on('npoapJacket.prod_order_id', '=', 'npo.id');
                $join->on('npoapJacket.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapJacket.element_id', '=', DB::raw("'4'"));  // == Jacket
                $join->on('npoapJacket.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapBanderole', function ($join) {
                $join->on('npoapBanderole.prod_order_id', '=', 'npo.id');
                $join->on('npoapBanderole.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapBanderole.element_id', '=', DB::raw("'5'"));  // == Jacket
                $join->on('npoapBanderole.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapEndpapers', function ($join) {
                $join->on('npoapEndpapers.prod_order_id', '=', 'npo.id');
                $join->on('npoapEndpapers.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapEndpapers.element_id', '=', DB::raw("'6'"));  // == Endpapers
                $join->on('npoapEndpapers.version', '=', DB::raw("'1'"));
            })
            ->select(
                'orders.id',
                'orders.data',
                'npo.id AS production_order_id',
                'npo.id AS distribution_amount',
                'npo.binding_id',
                'npo.color_id',
                'npoapText.color_id AS color_id_text',
                'npoapCover.color_id AS color_id_cover',
                'npoapInsert.color_id AS color_id_insert',
                'npoapJacket.color_id AS color_id_jacket',
                'npoapBanderole.color_id AS color_id_banderole',
                'npoapEndpapers.color_id AS color_id_endpapers',
                DB::raw("SUM(plBS.big_sheets) as bigSheets"),
                DB::raw("SUM(plNOST.number_of_sheets_text) as numberOfSheetsText")
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('plBS.created_at', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
                $q->orWhereBetween('plNOST.created_at', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->whereNull('orders.deleted_at')
            ->whereNull('plBS.deleted_at')
            ->whereNull('plNOST.deleted_at')
            ->groupBy('npo.id')
            ->get();
    }

    public function getProdLoadsNoFlaps($currentDays)
    {
        return DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->leftJoin('new_production_order_properties AS npoapText', function ($join) {
                $join->on('npoapText.prod_order_id', '=', 'npo.id');
                $join->on('npoapText.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapText.element_id', '=', DB::raw("'2'"));  // == Text
                $join->on('npoapText.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapCover', function ($join) {
                $join->on('npoapCover.prod_order_id', '=', 'npo.id');
                $join->on('npoapCover.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapCover.element_id', '=', DB::raw("'1'"));  // == Cover
                $join->on('npoapCover.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapInsert', function ($join) {
                $join->on('npoapInsert.prod_order_id', '=', 'npo.id');
                $join->on('npoapInsert.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapInsert.element_id', '=', DB::raw("'3'"));  // == Insert
                $join->on('npoapInsert.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapJacket', function ($join) {
                $join->on('npoapJacket.prod_order_id', '=', 'npo.id');
                $join->on('npoapJacket.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapJacket.element_id', '=', DB::raw("'4'"));  // == Jacket
                $join->on('npoapJacket.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapBanderole', function ($join) {
                $join->on('npoapBanderole.prod_order_id', '=', 'npo.id');
                $join->on('npoapBanderole.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapBanderole.element_id', '=', DB::raw("'5'"));  // == Jacket
                $join->on('npoapBanderole.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapEndpapers', function ($join) {
                $join->on('npoapEndpapers.prod_order_id', '=', 'npo.id');
                $join->on('npoapEndpapers.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapEndpapers.element_id', '=', DB::raw("'6'"));  // == Endpapers
                $join->on('npoapEndpapers.version', '=', DB::raw("'1'"));
            })
            ->select(
                'orders.id',
                'orders.data',
                'orders.copies',
                'npo.id AS production_order_id',
                'npo.distribution_amount',
                'npo.distribution',
                'npo.binding_id',
                'npo.color_id',
                'npoapText.color_id AS color_id_text',
                'npoapCover.color_id AS color_id_cover',
                'npoapInsert.color_id AS color_id_insert',
                'npoapJacket.color_id AS color_id_jacket',
                'npoapBanderole.color_id AS color_id_banderole',
                'npoapEndpapers.color_id AS color_id_endpapers',
                'npo.big_sheets AS bigSheets',
                'npo.number_of_sheets AS numberOfSheetsText'
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('orders.due_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
                $q->orWhereBetween('orders.additional_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->whereNull('orders.deleted_at')
//            ->where('npo.binding_id', 0)
//            ->groupBy('npo.id')
            ->groupBy('orders.id')
            ->get();
    }

    public function getProdLoadsWithFlaps($currentDays, $bindingIds)
    {
        return DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->leftJoin('new_production_order_properties AS npoapText', function ($join) {
                $join->on('npoapText.prod_order_id', '=', 'npo.id');
                $join->on('npoapText.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapText.element_id', '=', DB::raw("'2'"));  // == Text
                $join->on('npoapText.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapCover', function ($join) {
                $join->on('npoapCover.prod_order_id', '=', 'npo.id');
                $join->on('npoapCover.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapCover.element_id', '=', DB::raw("'1'"));  // == Cover
                $join->on('npoapCover.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapInsert', function ($join) {
                $join->on('npoapInsert.prod_order_id', '=', 'npo.id');
                $join->on('npoapInsert.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapInsert.element_id', '=', DB::raw("'3'"));  // == Insert
                $join->on('npoapInsert.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapJacket', function ($join) {
                $join->on('npoapJacket.prod_order_id', '=', 'npo.id');
                $join->on('npoapJacket.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapJacket.element_id', '=', DB::raw("'4'"));  // == Jacket
                $join->on('npoapJacket.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapBanderole', function ($join) {
                $join->on('npoapBanderole.prod_order_id', '=', 'npo.id');
                $join->on('npoapBanderole.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapBanderole.element_id', '=', DB::raw("'5'"));  // == Jacket
                $join->on('npoapBanderole.version', '=', DB::raw("'1'"));
            })
            ->leftJoin('new_production_order_properties AS npoapEndpapers', function ($join) {
                $join->on('npoapEndpapers.prod_order_id', '=', 'npo.id');
                $join->on('npoapEndpapers.opr_type_id', '=', DB::raw("'4'")); // == Technologist
                $join->on('npoapEndpapers.element_id', '=', DB::raw("'6'"));  // == Endpapers
                $join->on('npoapEndpapers.version', '=', DB::raw("'1'"));
            })
            ->select(
                'orders.id',
                'orders.data',
                'orders.copies',
                'npo.id AS production_order_id',
                'npo.distribution_amount',
                'npo.distribution',
                'npo.binding_id',
                'npo.color_id',
                'npoapText.color_id AS color_id_text',
                'npoapCover.color_id AS color_id_cover',
                'npoapInsert.color_id AS color_id_insert',
                'npoapJacket.color_id AS color_id_jacket',
                'npoapBanderole.color_id AS color_id_banderole',
                'npoapEndpapers.color_id AS color_id_endpapers',
                'npo.big_sheets AS bigSheets',
                'npo.number_of_sheets AS numberOfSheetsText'
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('orders.due_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
                $q->orWhereBetween('orders.additional_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->whereNull('orders.deleted_at')
            ->whereIn('npo.binding_id', $bindingIds)
//            ->groupBy('npo.id')
            ->groupBy('orders.id')
            ->get();
    }

    public function getDebugProdLoadBigSheets($currentDays)
    {
        return DB::table('production_loads AS pl')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'pl.production_order_id')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->select(
                DB::raw("SUM(pl.big_sheets) as bigSheets"),
                'orders.id AS orderId',
                'orders.data',
                'npo.id AS production_order_id',
                'npo.color_id',
                'npo.binding_id'
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('pl.created_at', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->where('pl.big_sheets', '>', 0)
            ->whereNull('pl.deleted_at')
            ->whereNull('orders.deleted_at')
            ->where('npo.version', 1)
            ->groupBy('npo.id')
            ->get();
    }

    public function getDebugProdLoadNumberOfSheets($currentDays)
    {
        return DB::table('production_loads AS pl')
            ->join('production_orders AS npo', 'npo.id', '=', 'pl.production_order_id')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->select(
                DB::raw("SUM(pl.number_of_sheets_text) as numberOfSheetsText"),
                'orders.id AS orderId',
                'orders.data',
                'npo.id AS production_order_id',
                'npo.color_id',
                'npo.binding_id'
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('pl.created_at', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->where('pl.number_of_sheets_text', '>', 0)
            ->whereNull('pl.deleted_at')
            ->whereNull('orders.deleted_at')
            ->groupBy('pl.id')
            ->get();
    }

    public function getProdLoads($currentDays)
    {
        return DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->select(
                'orders.id',
                'orders.name',
                'orders.data',
                'orders.copies',
                'npo.pages',
                'npo.color_id',
                'npo.binding_id',
                'npo.distribution',
                'npo.distribution_amount',
                'npo.distribution_bending',
                'npo.id AS production_order_id',
                'npo.production_number',
                'npo.big_sheets AS bigSheets',
                'npo.number_of_sheets AS numberOfSheetsText'
            )
            ->where(function ($q) use ($currentDays) {
                $q->whereBetween('orders.due_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
                $q->orWhereBetween('orders.additional_date', [
                    $currentDays['first_day'],
                    $currentDays['last_day']
                ]);
            })
            ->whereNull('orders.deleted_at')
            ->where('npo.version', 1)
//            ->groupBy('npo.id')
            ->groupBy('orders.id')
            ->get();
    }

    public function getProdOrderDataOnStore($newProdOrderId, $oprTypeId)
    {
        $roleTbl = null;

        foreach ($this->operatorTableNames() as $operatorTable) {
            if ($oprTypeId == $operatorTable['opr_type_id'] && $operatorTable['enabled'] == 1) {
                $roleTbl = $operatorTable['name'];
            }
        }

        if (!$roleTbl) {
            return [];
        }

        return DB::table('new_production_orders AS npo')
            ->join('new_production_order_properties AS npop', 'npop.prod_order_id', '=', 'npo.id')
            ->join('new_production_order_additional_properties AS npoap', function ($join) {
                $join->on('npoap.prod_order_id', '=', 'npop.prod_order_id');
                $join->on('npoap.element_id', '=', 'npop.element_id');
            })
            ->join('production_elements AS pe', 'pe.id', '=', 'npop.element_id')
            ->join('production_element_translations AS pet', function ($join) {
                $join->on('pet.element_id', '=', 'pe.id');
                $join->on('pet.language', '=', DB::raw("'{$this->language}'"));
            })
            ->join("production_{$roleTbl}_actions AS pa", 'pa.id', '=', 'npoap.action_id')
            ->join("production_{$roleTbl}_action_translations AS pas", function ($join) {
                $join->on('pas.action_id', '=', 'pa.id');
                $join->on('pas.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'npo.id AS npoId',
                'npo.color_id AS npoColorId',
                'npop.id AS npopId',
                'npop.pages AS npopPages',
                'npop.element_id AS npopElementId',
                'npop.color_id AS npopColorId',
                'npop.machine_id AS npopMachineId',
                'npop.big_sheets AS npopBigSheets',
                'npop.number_of_sheets AS npopNumberOfSheets',
                'npop.color_id AS npopColorId',
                'npop.paper_color_id AS npopPaperColorId',
                'npop.remarks AS npopRemarks',
                'npoap.id AS npoapId',
                'npoap.element_id AS npoapElementId',
                'npoap.action_id AS npoapActionId',
                'npoap.state_id AS npoapStateId',
                'npoap.remarks AS npoapRemarks',
                // use these
                'npop.pages',
                'npo.distribution',
                'npo.distribution_amount AS distributionAmount',
                'npo.distribution_bending AS distributionBending',
                'npop.element_id AS elementId',
                'pet.name AS elementName',
                'npop.color_id AS colorId',
                'npop.machine_id AS machineId',
                'npop.big_sheets AS bigSheets',
                'npop.number_of_sheets AS numberOfSheets',
                'npop.paper_size_id',
                'npop.paper_density_id',
                'npop.remarks',
                'npoap.action_id AS actionId',
                'pas.name AS actionName',
                'npoap.state_id AS stateId'
            )
            ->where('npo.id', $newProdOrderId)
            ->where('npo.version', 1)
            ->where('npop.version', 1)
            ->where('npoap.version', 1)
            ->where('npop.opr_type_id', $oprTypeId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->whereNull('pe.deleted_at')
            ->whereNull('pet.deleted_at')
            ->whereNull('pa.deleted_at')
            ->whereNull('pas.deleted_at')
            ->get();
    }

    public function getCurrentDataOnStore($newProdOrderId, $oprTypeId)
    {
        $roleTbl = null;

        foreach ($this->operatorTableNames() as $operatorTable) {
            if ($oprTypeId == $operatorTable['opr_type_id'] && $operatorTable['enabled'] == 1) {
                $roleTbl = $operatorTable['name'];
            }
        }

        if (!$roleTbl) {
            return [];
        }

        return DB::table('new_production_orders AS npo')
            ->join('new_production_order_properties AS npop', 'npop.prod_order_id', '=', 'npo.id')
            ->join('new_production_order_additional_properties AS npoap', function ($join) {
                $join->on('npoap.prod_order_id', '=', 'npop.prod_order_id');
                $join->on('npoap.element_id', '=', 'npop.element_id');
            })
            ->join('production_elements AS pe', 'pe.id', '=', 'npop.element_id')
            ->join('production_element_translations AS pet', function ($join) {
                $join->on('pet.element_id', '=', 'pe.id');
                $join->on('pet.language', '=', DB::raw("'{$this->language}'"));
            })
            ->join("production_{$roleTbl}_actions AS pa", 'pa.id', '=', 'npoap.action_id')
            ->join("production_{$roleTbl}_action_translations AS pas", function ($join) {
                $join->on('pas.action_id', '=', 'pa.id');
                $join->on('pas.language', '=', DB::raw("'{$this->language}'"));
            })
            ->join("production_{$roleTbl}_action_states AS pass", 'pass.id', '=', 'npoap.state_id')
            ->join("production_{$roleTbl}_action_state_translations AS passt", function ($join) {
                $join->on('passt.state_id', '=', 'pass.id');
                $join->on('passt.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select(
                'npo.id AS npoId',
                'npo.color_id AS npoColorId',
                'npop.id AS npopId',
                'npop.pages AS npopPages',
                'npop.element_id AS npopElementId',
                'npop.color_id AS npopColorId',
                'npop.machine_id AS npopMachineId',
                'npop.big_sheets AS npopBigSheets',
                'npop.number_of_sheets AS npopNumberOfSheets',
                'npop.color_id AS npopColorId',
                'npop.paper_color_id AS npopPaperColorId',
                'npop.remarks AS npopRemarks',
                'npoap.id AS npoapId',
                'npoap.element_id AS npoapElementId',
                'npoap.action_id AS npoapActionId',
                'npoap.state_id AS npoapStateId',
                'npoap.remarks AS npoapRemarks',
                'npop.paper_name_id AS npopPaperNameId',
                'npop.paper_size_id AS npopPaperSizeId',
                'npop.paper_color_id AS npopPaperColorId',
                'npop.paper_density_id AS npopPaperDensityId',
                'npop.paper_supplier_id AS npopPaperSupplierId',
                'npop.paper_delivered AS npopPaperDelivered',
                'npop.place AS npopPlace',
                'npop.bookmark AS npopBookmark',
                'npop.bookmark_insert AS npopBookmarkInsert',
                'npop.p_s_p_id AS npopPSPId',
                'npop.amount AS npopAmount',
                // use these
                'npop.place',
                'npop.bookmark',
                'npop.bookmark_insert AS bookmarkInsert',
                'npop.p_s_p_id AS pspId',
                'npop.amount',
                'npop.remarks',
                'npop.width',
                'npop.width_requested',
                'npop.paper_name AS paperName',
                'npop.paper_name_id AS paperNameId',
                'npop.paper_size_id AS paperSizeId',
                'npop.paper_color_id AS paperColorId',
                'npop.paper_density_id AS paperDensityId',
                'npop.paper_supplier_id AS paperSupplierId',
                'npop.paper_delivered AS paperDelivered',
                'npop.pages',
                'npop.element_id AS elementId',
                'pet.name AS elementName',
                'npop.color_id AS colorId',
                'npop.machine_id AS machineId',
                'npop.big_sheets AS bigSheets',
                'npop.number_of_sheets AS numberOfSheets',
                'npoap.action_id AS actionId',
                'pas.name AS actionName',
                'npoap.state_id AS stateId',
                'pass.hex AS stateHex',
                'passt.name AS stateName'
            )
            ->where('npo.id', $newProdOrderId)
            ->where('npo.version', 1)
            ->where('npop.version', 1)
            ->where('npoap.version', 1)
            ->where('npop.opr_type_id', $oprTypeId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->whereNull('pe.deleted_at')
            ->whereNull('pet.deleted_at')
            ->whereNull('pa.deleted_at')
            ->whereNull('pas.deleted_at')
            ->whereNull('pass.deleted_at')
            ->whereNull('passt.deleted_at')
            ->get();
    }

    public function getWidthWorkshop($prodOrderId, $elementId)
    {
        $width = 0;
        $widthRequested = 0;
        $realSpine = 0;
        $showRequestBtn = 0;
        $oprTypeId = 5; // 5 == Workshop

        $data = DB::table('new_production_orders AS npo')
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('new_production_order_properties AS npop', 'npo.id', '=', 'npop.prod_order_id')
            ->join('new_production_order_additional_properties AS npoap', 'npoap.prod_order_id', '=', 'npo.id')
            ->join('new_production_order_arranges AS npoa', function ($join) use ($oprTypeId) {
                $join->on('npoa.prod_order_id', '=', 'npo.id');
                $join->on('npoa.opr_type_id', '=', DB::raw("'{$oprTypeId}'"));
            })
            ->select(
                'orders.product_id',
                'npoap.state_id',
                'npo.real_spine',
                'npop.width',
                'npop.width_requested',
                'npop.paper_delivered'
            )
            ->where('npo.id', $prodOrderId)
            ->where('npop.opr_type_id', $oprTypeId)
            ->where('npop.element_id', $elementId)
            ->where('npoap.opr_type_id', $oprTypeId)
            ->where('npoa.order', '!=', 3)
            ->where('npoap.element_id', $elementId)
            ->where('npo.version', 1)
            ->where('npop.version', 1)
            ->where('npoap.version', 1)
            ->first();

        if ($data) {
            $width = $data->width;
            $realSpine = $data->real_spine;

            // Бутона - измерване на дебелина - не трябва да е активен за продуктите - Флаер, Дипляна и Плакат.
            $disabledProducts = [
                5, // flyer
                6, // depliant
                7, // affiches
            ];

            if (!in_array($data->product_id, $disabledProducts)) {
                $widthRequested = $data->width_requested;
                $paper_delivered = $data->paper_delivered;
                // Yes but we override :)
                $paper_delivered = 1;
                if (
                    $paper_delivered > 0
                    && $data->width_requested < 1
                ) {
                    $ids = [];
                    $states = $this->getElementActionStateRelations($oprTypeId, $elementId);
                    foreach ($states as $state) {
                        if ($state->is_final > 0) {
                            $ids[] = $state->state_id;
                        }
                    }
                    if (!in_array($data->state_id, $ids)) {
                        $showRequestBtn = 1;
                    }
                }
            }
        }

        return [
            'width' => $width,
            'widthRequested' => $widthRequested,
            'realSpine' => $realSpine,
            'showRequestBtn' => $showRequestBtn,
        ];
    }

    public function getElementName($id)
    {
        $name = 'ERROR';

        $element = DB::table('production_elements AS pe')
            ->join('production_element_translations AS pet', function ($join) {
                $join->on('pet.element_id', '=', 'pe.id');
                $join->on('pet.language', '=', DB::raw("'{$this->language}'"));
            })
            ->select('pe.id', 'pet.name')
            ->where('pe.id', $id)
            ->whereNull('pe.deleted_at')
            ->whereNull('pet.deleted_at')
            ->first();

        if ($element) {
            $name = $element->name;
        }

        return $name;
    }

    public function getWRPLogs()
    {
        return DB::table('new_production_order_histories AS npoh')
            ->join('new_production_orders AS npo', 'npo.id', '=', 'npoh.prod_order_id')
            ->leftJoin('production_operators AS po', 'po.id', '=', 'npoh.opr_id')
            ->select(
                'npo.order_id',
                'npoh.id',
                'npoh.text AS log',
                'po.id AS operatorId',
                'po.user_id AS userId',
                'po.name',
                DB::raw("DATE_FORMAT(npoh.created_at, '%d/%m/%Y %H:%i:%s') AS date")
            )
            ->where('npoh.opr_type_id', 7)
            ->orderBy('npoh.id', 'DESC')
            ->get();
    }

    public function wrpIndex()
    {
        return DB::table('new_production_orders AS npo')
            ->join('new_production_order_ready_copies AS nporcWRP', function ($join) {
                $join->on('nporcWRP.prod_order_id', '=', 'npo.id');
                $join->on('nporcWRP.version', '=', DB::raw("'1'"));
                $join->on('nporcWRP.opr_type_id', '=', DB::raw("'7'"));
            })
            ->join('new_production_order_ready_copies AS nporcCompletion', function ($join) {
                $join->on('nporcCompletion.prod_order_id', '=', 'npo.id');
                $join->on('nporcCompletion.version', '=', DB::raw("'1'"));
                $join->on('nporcCompletion.opr_type_id', '=', DB::raw("'2'"));
            })
            ->join('orders', 'orders.id', '=', 'npo.order_id')
            ->join('clients', 'orders.client_id', '=', 'clients.id')
            ->select(
                'npo.technologist_id',
                'nporcWRP.order',
                'nporcWRP.copies',
                'nporcWRP.is_ready',
                'nporcCompletion.is_ready AS editable',
                'orders.id AS orderId',
                'orders.name AS title',
                'orders.client_id',
                DB::raw("DATE_FORMAT(orders.due_date, '%d/%m/%Y') AS dueDate"),
                DB::raw("DATE_FORMAT(orders.additional_date, '%d/%m/%Y') AS additionalDate"),
                DB::raw("CONCAT_WS(' ',IFNULL(clients.name,''),IFNULL(clients.last_name,'')) AS clientNames")
            )
            ->whereNull('orders.deleted_at')
            ->whereNull('clients.deleted_at')
            ->groupBy('orders.id')
            ->orderBy('nporcWRP.order', 'ASC')
            ->orderBy('orders.due_date', 'ASC');
    }

    public function getAllBendings()
    {
        return [
            0 => ['id' => 0, 'name' => ['bg' => 'Без', 'en' => 'Без', 'fr' => 'Без']],
            1 => ['id' => 1, 'name' => ['bg' => '1 гънка', 'en' => '1 гънка', 'fr' => '1 гънка']],
            2 => ['id' => 2, 'name' => ['bg' => '2 гънки', 'en' => '2 гънки', 'fr' => '2 гънки']],
            3 => ['id' => 3, 'name' => ['bg' => '3 гънки', 'en' => '3 гънки', 'fr' => '3 гънки']],
            4 => ['id' => 4, 'name' => ['bg' => '4 гънки', 'en' => '4 гънки', 'fr' => '4 гънки']],
            5 => ['id' => 5, 'name' => ['bg' => '3 гънки хармоника', 'en' => '3 гънки хармоника', 'fr' => '3 гънки хармоника']],
            6 => ['id' => 6, 'name' => ['bg' => '4 гънки хармоника', 'en' => '4 гънки хармоника', 'fr' => '4 гънки хармоника']],
        ];
    }

    public function getBendings()
    {
        $results = [];

        foreach ($this->getAllBendings() as $bending) {
            $results[$bending['id']] = [
                'id' => $bending['id'],
                'name' => $this->getAllBendings()[$bending['id']]['name'][$this->language],
            ];
        }

        return json_decode(json_encode($results), false);
    }

    public function getBending($id)
    {
        $result = [
            'id' => $id,
            'name' => $this->getAllBendings()[$id]['name'][$this->language],
        ];

        return json_decode(json_encode($result), false);
    }

    public function tempCreateProdOrder($id)
    {
        return $this->orderTranslatorToProductionOrder($id);
    }
}