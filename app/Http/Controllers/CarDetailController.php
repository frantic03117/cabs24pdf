<?php

namespace App\Http\Controllers;



use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;


class CarDetailController extends Controller
{

    public function generate_pdf($id)
    {
        $items = Http::withOptions([
            'verify' => false,
        ])->get('https://api.cabx.co.in/api/v1/form/inspections/details/' . $id);
        $data = $items->json();
        $oimages = Http::withOptions([
            'verify' => false,
        ])->get('https://api.cabx.co.in/api/v1/form/inspections/other-images/?id=' . $id);
        $logodata = Http::withOptions([
            'verify' => false
        ])->get('https://api.cabx.co.in/api/v1/setting?title=Logo')->json();
        $filePath = str_replace('\\', '/', $logodata['data'][0]['file']);
        $logo =  'https://api.cabx.co.in/' . $filePath;
        $infos = Http::withOptions([
            'verify' => false
        ])->get('https://api.cabx.co.in/api/v1/setting')->json();
        $title = "";
        $website = "";
        $helpline = "";
        $address = "";
        foreach ($infos['data'] as $item) {
            if ($item['title'] == 'Website') {
                $website = $item['media_value'];
            }
            if ($item['title'] == 'Title') {
                $title = $item['media_value'];
            }
            if ($item['title'] == 'Helpline') {
                $helpline = $item['media_value'];
            }
            if ($item['title'] == 'Address') {
                $address = $item['media_value'];
            }
        }
        $other_imgs = $oimages->json();
        $dets = [];
        $details = [];
        $carimages = [];
        $filteredImages = array_filter($data['data']['inspections'], function ($val) {
            return $val['key']['type'] === "image" && $val['key']['field'] !== "rc_image";
        });
        usort($filteredImages, function ($a, $b) {
            if ($a['step'] === $b['step']) {

                return $a['key']['order'] <=> $b['key']['order'];
            }
            return $a['step'] <=> $b['step'];
        });
        foreach ($filteredImages as $k => $val) {

            if ($val['key']['type'] == "image" && $val['key']['field'] != "rc_image") {
                $irr = [
                    'image' => $val['string_value'],
                    'name' => $val['key']['name'],
                    "store_at" => $val['store_at'],
                    'created_at' => Carbon::parse($val['createdAt'])
                        ->addHours(5)
                        ->addMinutes(30)
                        ->format('M-d, Y h:i A')
                ];
                array_push($carimages, $irr);
            }
        }
        foreach ($other_imgs['data'] as $k => $val) {
            $irr = [
                'image' => $val['image'],
                'name' => 'Other Image',
                "store_at" => $val?->store_at,
                'created_at' => Carbon::parse($val['createdAt'])
                    ->addHours(5)
                    ->addMinutes(30)
                    ->format('M-d, Y h:i A')
            ];
            array_push($carimages, $irr);
        }
        $collection = collect($data['data']['inspections']);
        foreach ($collection as $col) {
            $key = $col['key']['field'];
            $item = $collection->firstWhere('key.field', $key);
            $details[$key] = $item['string_value'];
        }
        $rats = $collection->where('key.type', 'rating')->toArray();
        $details['inspection_id'] = $data['data']['inspection_id'];
        $details['custom_inspection_id'] = $data['data']['custom_inspection_id'];
        $details['executive_name'] = $data['data']['user']['name'];
        $details['address'] = $data['data']['location']['address'];
        $details['registration_number'] = $data['data']['registration_number'];
        $details['doi'] = $data['data']['createdAt'];
        $res = compact('details', 'carimages', 'rats', 'logo', 'title', 'website', 'helpline', 'address');
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        // return response()->json($res);
        // return view('pdf.test', $res);
        // die;
        $pdf = PDF::setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,

            'tempDir' => storage_path('app')
        ])
            ->loadView('pdf.test', $res)
            ->setPaper('a4');
        $pdf->getDomPDF()->setHttpContext($context);
        $domPdf = $pdf->getDomPDF();
        $domPdf->set_option('isPhpEnabled', true);
        $domPdf->render();
        $canvas = $domPdf->get_canvas();
        $canvas->page_text(
            $canvas->get_width() - 80,
            $canvas->get_height() - 30,
            "Page {PAGE_NUM} of {PAGE_COUNT}",
            null,
            10,
            [0, 0, 0],
            1
        );
        $fname =  preg_replace('/[\/\\\\]/', '', $details['select_variant']);
        return $pdf->stream($details['registration_number'] . '-' . $fname . '.pdf');
    }
}