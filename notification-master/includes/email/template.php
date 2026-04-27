<?php
/**
 * Email Template
 *
 * This template is used to generate the styled email body.
 *
 * @package notification-master
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

$dir = is_rtl() ? 'rtl' : 'ltr';
?>
<!doctype html>
<html dir="<?php echo esc_attr( $dir ); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
	<body style="height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; padding: 0; margin: 0; Margin: 0; mso-line-height-rule: exactly; background-color: #ffffff;">
	<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" style="border-collapse: collapse; border-spacing: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; background-color: #ffffff;padding: 0; margin: 0; Margin: 0; mso-line-height-rule: exactly;">
		<tr style="padding: 0; vertical-align: top;">
			<td align="center" valign="top"  style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; padding: 0; margin: 0; Margin: 0; mso-line-height-rule: exactly; text-align: center;">
				<!-- Container -->
				<table border="0" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; width: 600px; margin: 0; Margin: 0; text-align: inherit;">
					<!-- Content -->
					<tr style="padding: 0; vertical-align: top;">
						<td align="left" valign="top" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;  margin: 0; Margin: 0; mso-line-height-rule: exactly; background-color: #ffffff;">
							<?php echo $args['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
