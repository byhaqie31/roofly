{{--
  Shared shell for every Roofly email (table layout + inline styles so it
  survives Gmail/Outlook). Children set the strings via @section and pass
  $ctaUrl / $ctaLabel from the notification (both optional — no URL, no button):
    @section('title')     the <title>
    @section('tag')       right-aligned header label, e.g. WELCOME ABOARD
    @section('pill')      orange pill above the headline (optional)
    @section('headline')  <h1> contents (may contain <br> / accent <span>)
    @section('body')      paragraphs between headline and button
    @section('closing')   paragraph after the button (optional)
    @section('fineprint') small print above "Button not working?" (optional)
    @section('reason')    footer "you're receiving this because…" line
  The legal footer (App\Support\EmailFooter) is added here, never per email.
  Transactional emails don't extend this directly — they go through
  emails/notice via App\Support\NoticeMail.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title')</title>
</head>

<body style="margin:0; padding:0; background:#F3F1EC; font-family:Arial,Helvetica,sans-serif;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
    style="background:#F3F1EC;">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
          style="max-width:600px;">

          <!-- Roofly header -->
          <tr>
            <td style="background:#1C1B17; padding:28px 32px; border-radius:16px 16px 0 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="font-size:25px; font-weight:bold; letter-spacing:-1px; color:#F8F6EF;">
                    Roofly<span style="color:#DD7047;">.</span><span style="font-size:18px;">my</span>
                  </td>
                  <td align="right"
                    style="font-size:10px; font-weight:bold; letter-spacing:2px; color:#E9AE95;">
                    @yield('tag')
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Orange accent -->
          <tr>
            <td height="4" style="background:#DD7047; font-size:0; line-height:4px;">
              &nbsp;
            </td>
          </tr>

          <!-- Email body -->
          <tr>
            <td style="background:#FFFEFA; padding:36px 32px; border-radius:0 0 16px 16px;">

              @php
                $hasPill = trim($__env->yieldContent('pill')) !== '';
                $hasFineprint = trim($__env->yieldContent('fineprint')) !== '';
                $hasClosing = trim($__env->yieldContent('closing')) !== '';
              @endphp

              @if ($hasPill)
                <table role="presentation" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="background:#FAEBE3; padding:8px 12px; border-radius:20px;
                      font-size:10px; font-weight:bold; letter-spacing:1.5px; color:#9A4427;">
                      @yield('pill')
                    </td>
                  </tr>
                </table>
              @endif

              <h1 style="margin:{{ $hasPill ? '24px' : '0' }} 0 24px; font-size:36px; line-height:42px;
                letter-spacing:-1.5px; color:#24231F;">
                @yield('headline')
              </h1>

              @yield('body')

              @if (! empty($ctaUrl))
                <!-- CTA button -->
                <table role="presentation" cellpadding="0" cellspacing="0">
                  <tr>
                    <td align="center" bgcolor="#DD7047"
                      style="background:#DD7047; border-radius:8px; mso-padding-alt:17px 24px;">
                      <a href="{{ $ctaUrl }}"
                        target="_blank"
                        style="display:inline-block; padding:17px 24px;
                          border:1px solid #DD7047; border-radius:8px;
                          font-size:15px; line-height:20px; font-weight:bold;
                          color:#211C18; text-decoration:none;">
                        {{ $ctaLabel }} &nbsp;→
                      </a>
                    </td>
                  </tr>
                </table>
              @endif

              @if ($hasClosing)
                <p style="margin:{{ empty($ctaUrl) ? '0' : '28px' }} 0 0; font-size:16px; line-height:26px; color:#4F4C45;">
                  @yield('closing')
                </p>
              @endif

              <p style="margin:{{ $hasClosing || empty($ctaUrl) ? '20px' : '28px' }} 0 0; font-size:15px; line-height:24px; color:#4F4C45;">
                Warm regards,<br>
                <strong style="color:#24231F;">The Roofly Team</strong>
              </p>

              @if ($hasFineprint || ! empty($ctaUrl))
                <!-- Small print + fallback link -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                  style="margin-top:32px;">
                  <tr>
                    <td style="border-top:1px solid #E8E4DC; padding-top:22px;
                      font-size:12px; line-height:20px; color:#79746A;">
                      @yield('fineprint')
                      @if (! empty($ctaUrl))
                        Button not working?
                        <a href="{{ $ctaUrl }}"
                          style="color:#9A4427; text-decoration:underline;">
                          Open it here</a>.
                      @endif
                    </td>
                  </tr>
                </table>
              @endif

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center"
              style="padding:24px 16px; font-size:12px; line-height:20px; color:#79746A;">
              <strong style="color:#5C574E;">Property management, reimagined.</strong>
              <br>
              @yield('reason')
            </td>
          </tr>

          <!-- Legal footer -->
          <tr>
            <td align="center"
              style="padding:0 16px 32px; font-size:11px; line-height:18px; color:#79746A;">
              @foreach (\App\Support\EmailFooter::links() as $label => $href)
                <a href="{{ $href }}" target="_blank"
                  style="color:#5C574E; text-decoration:underline;">{{ $label }}</a>@if (! $loop->last)&nbsp;&nbsp;·&nbsp;&nbsp;@endif
              @endforeach
              <br>
              {{ \App\Support\EmailFooter::operatorLine() }}
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
