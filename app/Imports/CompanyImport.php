<?php

namespace App\Imports;

use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CompanyImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) 
        {
            if ($key === 0 || $row[0] === 'カテゴリ') {
                continue;
            }
            try {
                $category = \App\Models\Source::where('name', $row[0])->first();
                
                if (empty($category)) {
                    $lastCategory = \App\Models\Source::orderByDesc('sort_no')->first();
                    if(!isset($lastCategory)){
                        $category = \App\Models\Source::create([
                            'name'          => $row[0],
                            'sort_no'       => 1
                        ]);
                    }else{
                        $category = \App\Models\Source::create([
                            'name'          => $row[0],
                            'sort_no'       => $lastCategory->sort_no + 1
                        ]);
                    }
                    
                }
                if($row[1]){
                    $child_category = \App\Models\SubSource::where('name', $row[1])->first();

                    if(empty($child_category)&&isset($category)) {
    
                        $child_lastCategory = \App\Models\SubSource::orderByDesc('sort_no')->first();
                    
                        if(!isset($child_lastCategory)){
                            $child_category = \App\Models\SubSource::create([
                                'source_id'     => $category->id,
                                'name'          => $row[1],
                                'sort_no'       => 1
                            ]);
                        }else{
                            $child_category = \App\Models\SubSource::create([
                                'source_id'     => $category->id,
                                'name'          => $row[1],
                                'sort_no'       => $child_lastCategory->sort_no + 1
                            ]);
                        }
    
                    }
                }
               
				if(is_null($row[6])){
                    $row[6]="未対応";
                }
                
                if (isset($category)) {
                    if(strpos($row[3],"http")!==false){
                        //
                    }else {
                        $row[3]="http://".$row[3];
                    }
                    $parse = parse_url($row[3]);
                    if(isset($parse['host'])) {
                        $host = str_replace('www.', '', $parse['host']);
                        $url = $parse['scheme'].'://'.$parse['host'];
                        if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                            $company = Company::create([
                                'source'        => $row[0],
                                'subsource'     => $row[1],
                                'name'          => $row[2],
                                'url'           => $url,
                                'contact_form_url'           => $row[4],
                                'area'          => $row[5],
                                'status'          => $row[6]
                            ]);
    
                            if (isset($row[7]) && $row[7] != '') {
                                $company->phones()->updateOrCreate([
                                    'phone'         => $row[7]
                                ]);
                            }
                        } 
                    }else { 
                        $host = str_replace('www.', '', $row[3]);
                        if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                            $company = Company::create([
                                'source'        => $row[0],
                                'subsource'     => $row[1],
                                'name'          => $row[2],
                                'url'           => $row[3],
                                'contact_form_url'           => $row[4],
                                'area'          => $row[5],
                                'status'          => $row[6]
                            ]);

                            if (isset($row[7]) && $row[7] != '') {
                                $company->phones()->updateOrCreate([
                                    'phone'         => $row[7]
                                ]);
                            }
                        }
                    }
                   
                }
                
            } catch (\Throwable $e) {
                continue;
            }
           
        }
    }
}