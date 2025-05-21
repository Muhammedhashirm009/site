<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require('fpdf/fpdf.php');

    function convertNumberToWords($number) {
        $hyphen = '-'; $conjunction = ' and '; $separator = ', ';
        $negative = 'Negative '; $decimal = ' point ';
        $dictionary = [
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen',
            17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
            100 => 'Hundred', 1000 => 'Thousand', 100000 => 'Lakh', 10000000 => 'Crore'
        ];
        if (!is_numeric($number)) return false;
        if ($number < 0) return $negative . convertNumberToWords(abs($number));
        $string = null;
        if (strpos($number, '.') !== false) list($number, $fraction) = explode('.', $number);
        switch (true) {
            case $number < 21: $string = $dictionary[$number]; break;
            case $number < 100:
                $tens = ((int) ($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) $string .= $hyphen . $dictionary[$units];
                break;
            case $number < 1000:
                $hundreds = (int) ($number / 100);
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) $string .= $conjunction . convertNumberToWords($remainder);
                break;
            case $number < 100000:
                $thousands = (int) ($number / 1000);
                $remainder = $number % 1000;
                $string = convertNumberToWords($thousands) . ' ' . $dictionary[1000];
                if ($remainder) $string .= $separator . convertNumberToWords($remainder);
                break;
            case $number < 10000000:
                $lakhs = (int) ($number / 100000);
                $remainder = $number % 100000;
                $string = convertNumberToWords($lakhs) . ' ' . $dictionary[100000];
                if ($remainder) $string .= $separator . convertNumberToWords($remainder);
                break;
            default:
                $crores = (int) ($number / 10000000);
                $remainder = $number % 10000000;
                $string = convertNumberToWords($crores) . ' ' . $dictionary[10000000];
                if ($remainder) $string .= $separator . convertNumberToWords($remainder);
                break;
        }
        if (isset($fraction) && is_numeric($fraction)) {
            $string .= $decimal;
            foreach (str_split((string) $fraction) as $digit) {
                $string .= $dictionary[$digit] . ' ';
            }
        }
        return $string;
    }

    class InvoicePDF extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',16);
            $this->Cell(0,10,'MICROCONTROLLER KADACHIRA',0,1,'C');
            $this->SetFont('Arial','',12);
            $this->Cell(0,6,'Kadachira main road 670621',0,1,'C');
            $this->Cell(0,6,'Mob: 8921762828',0,1,'C');
            $this->Ln(10);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',10);
            $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
        }
    }

    $invoiceNo = $_POST['invoiceNo'] ?? 'N/A';
    $invoiceDate = $_POST['invoiceDate'] ?? date('Y-m-d');
    $buyerName = $_POST['buyerName'] ?? 'Customer';
    $buyerState = $_POST['buyerState'] ?? '';
    $buyerMobile = $_POST['buyerMobile'] ?? '';
    $buyerAddress = $_POST['buyerAddress'] ?? '';
    $paymentTerms = $_POST['paymentTerms'] ?? '';
    $dueDate = $_POST['dueDate'] ?? '';
    $deliveryNote = $_POST['deliveryNote'] ?? '';
    $paymentMethod = $_POST['paymentMethod'] ?? '';
    $utrId = $_POST['utrId'] ?? '';
    $desc = $_POST['desc'] ?? [];
    $hsn = $_POST['hsn'] ?? [];
    $qty = $_POST['qty'] ?? [];
    $rate = $_POST['rate'] ?? [];
    $roundOff = floatval($_POST['roundOff'] ?? 0);

    $pdf = new InvoicePDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(100,8,"Invoice No: $invoiceNo",0,0);
    $pdf->Cell(0,8,"Date: $invoiceDate",0,1);
    $pdf->Cell(100,8,"Buyer: $buyerName",0,0);
    $pdf->Cell(0,8,"State: $buyerState",0,1);
    $pdf->Cell(100,8,"Mobile: $buyerMobile",0,0);
    $pdf->Cell(0,8,"Payment Terms: $paymentTerms",0,1);
    if (strtolower(trim($paymentTerms)) === 'due on date' && !empty($dueDate)) {
        $pdf->Cell(100,8,"Due Date: $dueDate",0,1);
    }
    $pdf->Cell(100,8,"Address: $buyerAddress",0,0);
    $pdf->Cell(0,8,"Delivery Note: $deliveryNote",0,1);
    $pdf->Ln(10);
    $pdf->Cell(100,8,"Payment Method: $paymentMethod",0,1);
    if (strtolower(trim($paymentMethod)) === 'upi' && !empty($utrId)) {
        $pdf->Cell(100,8,"UPI Transaction UTR ID: $utrId",0,1);
    }
    $pdf->Ln(10);

    // Items Table
    $pdf->SetFont('Arial','B',12);
    $pdf->SetFillColor(230,230,230);
    $pdf->Cell(10,10,"#",1,0,'C', true);
    $pdf->Cell(60,10,"Description",1,0,'C', true);
    $pdf->Cell(30,10,"HSN",1,0,'C', true);
    $pdf->Cell(20,10,"Qty",1,0,'C', true);
    $pdf->Cell(30,10,"Rate",1,0,'C', true);
    $pdf->Cell(30,10,"Amount",1,1,'C', true);

    $pdf->SetFont('Arial','',12);
    $total = 0;
    for ($i = 0; $i < count($desc); $i++) {
        $d = $desc[$i] ?? '';
        $h = $hsn[$i] ?? '';
        $q = floatval($qty[$i] ?? 0);
        $r = floatval($rate[$i] ?? 0);
        $amt = $q * $r;
        $total += $amt;

        $pdf->Cell(10,10,$i+1,1,0,'C');
        $pdf->Cell(60,10,$d,1,0,'L');
        $pdf->Cell(30,10,$h,1,0,'C');
        $pdf->Cell(20,10,$q,1,0,'C');
        $pdf->Cell(30,10,number_format($r, 2),1,0,'R');
        $pdf->Cell(30,10,number_format($amt, 2),1,1,'R');
    }

    $grandTotal = $total + $roundOff;
    $pdf->Ln(5);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(150,10,"Total Amount",1,0,'R');
    $pdf->Cell(30,10,number_format($total, 2),1,1,'R');
    $pdf->Cell(150,10,"Round Off",1,0,'R');
    $pdf->Cell(30,10,number_format($roundOff, 2),1,1,'R');
    $pdf->Cell(150,10,"Grand Total",1,0,'R');
    $pdf->Cell(30,10,number_format($grandTotal, 2),1,1,'R');
    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,"Grand Total in Words:", 0, 1);
    $pdf->SetFont('Arial','I',12);
    $pdf->MultiCell(0,10,ucwords(convertNumberToWords($grandTotal)) . ' Rupees Only.');
    $pdf->Ln(10);
    $pdf->SetFont('Arial','I',10);
    $pdf->Cell(0,6,"This is a system-generated invoice. No signature required.",0,1,'C');
    $pdf->Output("I", "Invoice_$invoiceNo.pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Invoice</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <h2>Create Invoice</h2>
  <form method="POST">
    <label>Invoice No</label>
    <input type="text" name="invoiceNo" required />
    <label>Date</label>
    <input type="date" name="invoiceDate" required />
    <label>Buyer Name</label>
    <input type="text" name="buyerName" required />
    <label>State</label>
    <input type="text" name="buyerState" required />
    <label>Mobile</label>
    <input type="tel" name="buyerMobile" required />
    <label>Address</label>
    <textarea name="buyerAddress" rows="3"></textarea>
    <label>Payment Terms</label>
    <select name="paymentTerms" id="paymentTerms">
      <option value="Immediate">Immediate</option>
      <option value="Due on Date">Due on Date</option>
    </select>
    <div id="dueDateContainer" style="display: none;">
      <label>Due Date</label>
      <input type="date" name="dueDate" />
    </div>
    <label>Delivery Note</label>
    <input type="text" name="deliveryNote" />
    <label>Payment Method</label>
    <select name="paymentMethod" id="paymentMethod">
      <option value="Cash">Cash</option>
      <option value="UPI">UPI</option>
      <option value="Bank Transfer">Bank Transfer</option>
    </select>
    <div id="utrIdContainer" style="display: none;">
      <label>UTR ID (If UPI)</label>
      <input type="text" name="utrId" />
    </div>

    <h3>Items</h3>
    <div id="items-container"></div>
    <button type="button" class="add-btn" onclick="addItem()">+ Add Item</button>
    <label>Round Off</label>
    <input type="number" name="roundOff" step="0.01" value="0" />
    <button type="submit">Generate Invoice</button>
  </form>

  <script>
    function addItem() {
      const div = document.createElement('div');
      div.className = 'item-row';
      div.innerHTML = `
        <label>Description</label><input type="text" name="desc[]" required />
        <label>HSN</label><input type="text" name="hsn[]" />
        <label>Quantity</label><input type="number" name="qty[]" required />
        <label>Rate</label><input type="number" name="rate[]" required />
        <button type="button" class="remove-btn" onclick="this.parentNode.remove()">Remove</button>
      `;
      document.getElementById('items-container').appendChild(div);
    }
    window.onload = addItem;
    document.getElementById('paymentTerms').addEventListener('change', function() {
      document.getElementById('dueDateContainer').style.display = this.value === 'Due on Date' ? 'block' : 'none';
    });
    document.getElementById('paymentMethod').addEventListener('change', function() {
      document.getElementById('utrIdContainer').style.display = this.value === 'UPI' ? 'block' : 'none';
    });
  </script>
</body>
</html>
