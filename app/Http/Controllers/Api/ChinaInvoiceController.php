<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ChinaInvoice;
use App\Models\ChinaInvoiceTemplate;
use App\Models\ChinaTaxDevice;
use App\Models\ChinaTaxReport;
use App\Services\ChinaInvoiceService;
use Illuminate\Http\Request;

class ChinaInvoiceController extends Controller
{
    public function __construct(protected ChinaInvoiceService $invoiceService) {}

    // ── 税控设备 ──
    public function devices() {
        return response()->json(['success'=>true,'data'=>ChinaTaxDevice::orderByDesc('created_at')->get()]);
    }
    public function storeDevice(Request $request) {
        $validated = $request->validate([
            'name'=>'required|string|max:100','device_type'=>'required|in:ukey,tax_disk,cloud',
            'taxpayer_id'=>'required|string|max:30','company_name'=>'required|string|max:200',
            'registered_address'=>'nullable|string','phone'=>'nullable|string','bank_name'=>'nullable|string','bank_account'=>'nullable|string',
            'tax_authority'=>'nullable|string',
        ]);
        $d = ChinaTaxDevice::create(array_merge($validated,[
            'tenant_id'=>$request->user()->tenant_id??1,
            'tax_authority'=>$validated['tax_authority']??'国家税务总局',
        ]));
        return response()->json(['success'=>true,'data'=>$d],201);
    }
    public function destroyDevice(ChinaTaxDevice $chinaTaxDevice) {
        $chinaTaxDevice->delete();
        return response()->json(['success'=>true]);
    }

    // ── 发票模板 ──
    public function templates() {
        return response()->json(['success'=>true,'data'=>ChinaInvoiceTemplate::orderByDesc('created_at')->get()]);
    }
    public function storeTemplate(Request $request) {
        $validated = $request->validate([
            'name'=>'required|string','invoice_type'=>'required|in:vat_special,vat_normal,fiscal_bill,receipt',
            'title'=>'required|string','is_electronic'=>'boolean',
        ]);
        $t = ChinaInvoiceTemplate::create(array_merge($validated,['tenant_id'=>$request->user()->tenant_id??1]));
        return response()->json(['success'=>true,'data'=>$t],201);
    }
    public function destroyTemplate(ChinaInvoiceTemplate $chinaInvoiceTemplate) {
        $chinaInvoiceTemplate->delete();
        return response()->json(['success'=>true]);
    }

    // ── 发票 ──
    public function invoices(Request $request) {
        $q = ChinaInvoice::with('items');
        if ($request->status) $q->where('status', $request->status);
        if ($request->invoice_type) $q->where('invoice_type', $request->invoice_type);
        return response()->json(['success'=>true,'data'=>$q->orderByDesc('created_at')->paginate(20)]);
    }

    public function issue(Request $request) {
        $validated = $request->validate([
            'invoice_type'=>'required|in:vat_special,vat_normal,fiscal_bill',
            'template_id'=>'nullable|integer|exists:china_invoice_templates,id',
            'order_id'=>'nullable|integer|exists:orders,id',
            'buyer_name'=>'required|string|max:200',
            'buyer_tax_id'=>'nullable|string|max:30',
            'amount'=>'required|numeric|min:0.01',
            'tax_rate'=>'required|numeric|in:0,3,6,9,13',
            'items'=>'required|array|min:1',
            'items.*.item_name'=>'required|string',
            'items.*.quantity'=>'required|integer|min:1',
            'items.*.unit_price'=>'required|numeric|min:0',
            'items.*.tax_rate'=>'required|numeric',
            'remark'=>'nullable|string',
        ]);
        $validated['tax_amount'] = round($validated['amount'] * $validated['tax_rate'] / 100, 2);
        $validated['tenant_id'] = $request->user()->tenant_id ?? 1;
        foreach ($validated['items'] as &$item) {
            $item['amount'] = round($item['quantity'] * $item['unit_price'], 2);
            $item['tax_amount'] = round($item['amount'] * $item['tax_rate'] / 100, 2);
        }
        $invoice = $this->invoiceService->issueInvoice($validated);
        return response()->json(['success'=>true,'data'=>$invoice->load('items')],201);
    }

    public function show(ChinaInvoice $chinaInvoice) {
        return response()->json(['success'=>true,'data'=>$chinaInvoice->load('items')]);
    }

    public function redLetter(Request $request, ChinaInvoice $chinaInvoice) {
        $validated = $request->validate(['reason'=>'nullable|string']);
        $red = $this->invoiceService->redLetter($chinaInvoice, $validated['reason']??'');
        return response()->json(['success'=>true,'data'=>$red->load('items')]);
    }

    public function void(ChinaInvoice $chinaInvoice) {
        $this->invoiceService->voidInvoice($chinaInvoice);
        return response()->json(['success'=>true]);
    }

    // ── 税务报告 ──
    public function taxReports(Request $request) {
        $tenantId = $request->user()->tenant_id ?? 1;
        return response()->json(['success'=>true,'data'=>ChinaTaxReport::where('tenant_id',$tenantId)->orderByDesc('period')->get()]);
    }
    public function generateTaxReport(Request $request) {
        $validated = $request->validate(['period'=>'required|date_format:Y-m']);
        $tenantId = $request->user()->tenant_id ?? 1;
        $r = $this->invoiceService->generateTaxReport($tenantId, $validated['period']);
        return response()->json(['success'=>true,'data'=>$r],201);
    }

    public function stats() {
        $tid = request()->user()->tenant_id ?? 1;
        return response()->json(['success'=>true,'data'=>[
            'total_invoices'=>ChinaInvoice::where('tenant_id',$tid)->count(),
            'issued_invoices'=>ChinaInvoice::where('tenant_id',$tid)->where('status','issued')->count(),
            'pending_invoices'=>ChinaInvoice::where('tenant_id',$tid)->where('status','pending')->count(),
            'total_reports'=>ChinaTaxReport::where('tenant_id',$tid)->count(),
            'monthly_amount'=>ChinaInvoice::where('tenant_id',$tid)->where('status','issued')->whereMonth('issued_at',now()->month)->sum('total_amount'),
        ]]);
    }
}
