{{--
  Shared Roofly marketing-email shell (table layout + inline styles so it
  survives Gmail/Outlook). Children set the strings via @section and pass
  $ctaUrl / $ctaLabel from the notification:
    @section('title')    the <title>
    @section('tag')      right-aligned header label, e.g. WELCOME ABOARD
    @section('pill')     orange pill above the headline
    @section('headline') <h1> contents (may contain <br> / accent <span>)
    @section('body')     paragraphs between headline and button
    @section('closing')  paragraph after the button
    @section('reason')   footer "you're receiving this because…" line
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

              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:#FAEBE3; padding:8px 12px; border-radius:20px;
                    font-size:10px; font-weight:bold; letter-spacing:1.5px; color:#9A4427;">
                    @yield('pill')
                  </td>
                </tr>
              </table>

              <h1 style="margin:24px 0; font-size:36px; line-height:42px;
                letter-spacing:-1.5px; color:#24231F;">
                @yield('headline')
              </h1>

              @yield('body')

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

              <p style="margin:28px 0 0; font-size:16px; line-height:26px; color:#4F4C45;">
                @yield('closing')
              </p>

              <p style="margin:20px 0 0; font-size:15px; line-height:24px; color:#4F4C45;">
                Warm regards,<br>
                <strong style="color:#24231F;">The Roofly Team</strong>
              </p>

              <!-- Fallback link -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                style="margin-top:32px;">
                <tr>
                  <td style="border-top:1px solid #E8E4DC; padding-top:22px;
                    font-size:12px; line-height:20px; color:#79746A;">
                    Button not working?
                    <a href="{{ $ctaUrl }}"
                      style="color:#9A4427; text-decoration:underline;">
                      Open it here</a>.
                  </td>
                </tr>
              </table>

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

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
