@php
    /** @var ?string $paypal */
    /** @var ?string $alipay */
    /** @var string $custom */
    /** @var string $siteName */
    /** @var string $returnUrl */
    /** @var string $cancelUrl */
    /** @var int $accountantId */
    /** @var list<int> $amounts */

    $hasBoth = $paypal !== null && $alipay !== null;
    $tdAttr = $hasBoth ? 'width="50%"' : 'colspan="2" width="100%"';
@endphp

<h2>Donate</h2>
<table width="100%">
<tr>
    <td colspan="2" class="text" align="left">
        If you like the site, please consider donating to help cover the
        running costs. After donating, send us a message with your username
        so we can credit your account.
    </td>
</tr>

@if ($custom !== '')
    <tr>
        <td class="text" align="left" colspan="2">{!! $custom !!}</td>
    </tr>
@endif

<tr>
@if ($paypal !== null)
    <td class="text" align="left" valign="top" {!! $tdAttr !!}>
        <b>Donate with PayPal</b><br /><br />
        Use the PayPal form below to donate.
        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
            <input type="hidden" name="cmd" value="_xclick">
            <input type="hidden" name="business" value="{{ $paypal }}">
            <input type="hidden" name="item_name" value="Donation to {{ $siteName }}">
            <p align="center"><br />
                Select donation amount:<br />
                <select name="amount">
                    <option value="" selected>Other amount</option>
                    @foreach ($amounts as $amount)
                        @php($formatted = number_format((float) $amount, 2, '.', ''))
                        <option value="{{ $formatted }}">USD {{ $formatted }} donation</option>
                    @endforeach
                </select>
                <input type="hidden" name="image_url" value="">
                <input type="hidden" name="shipping" value="0">
                <input type="hidden" name="currency_code" value="USD">
                <input type="hidden" name="return" value="{{ $returnUrl }}">
                <input type="hidden" name="cancel_return" value="{{ $cancelUrl }}">
                <br />
            </p>
            <p align="center">
                <input type="image" src="pic/paypalbutton.gif" border="0" name="I1" alt="Make payments with PayPal">
                <br /><br />
            </p>
        </form>
    </td>
@endif

@if ($alipay !== null)
    <td class="text" align="left" valign="top" {!! $tdAttr !!}>
        <b>Donate with Alipay</b><br /><br />
        <form action="https://www.alipay.com/trade/fast_pay.htm" method="get">
            Use Alipay account <b>{{ $alipay }}</b> to transfer your donation.
            <br /><br /><br /><br /><br />
            <p align="center">
                <input type="image" src="pic/alipaybutton.gif" border="0" name="I2" alt="Make payments with Alipay" />
                <br /><br />
            </p>
        </form>
    </td>
@endif
</tr>

<tr>
    <td class="text" colspan="2" align="left">
        After donating please
        <a href="sendmessage.php?receiver={{ $accountantId }}"><font class="striking"><b>send us</b></font></a>
        a message with your username so we can credit your account.
    </td>
</tr>
</table>
