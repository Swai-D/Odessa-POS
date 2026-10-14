<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
	<meta charset="utf-8">
	<title>{{ $statement->number }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; }

	.stmt { font-size: 12px; color: #212b36; }
	.stmt .r { text-align: right; }
	.stmt .strong { font-weight: bold; }
	.stmt h3 { font-size: 14px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
	.stmt table { width: 100%; border-collapse: collapse; }
	.stmt-head td { vertical-align: top; padding: 0; }
	.stmt-title { font-size: 22px; font-weight: bold; }
	.stmt-shop { font-size: 16px; margin-top: 2px; }
	.stmt-grid td { padding: 3px 4px; border-bottom: 1px solid #eee; }
	.stmt-table th, .stmt-table td { padding: 4px 3px; font-size: 10.5px; border-bottom: 1px solid #eee; }
	.stmt-table th { border-bottom: 1px solid #999; text-align: left; }
	.stmt-table th.r { text-align: right; }
	.stmt-table tr.total td { font-weight: bold; border-top: 1px solid #999; }
	.stmt-verify { margin-top: 20px; border: 1px solid #999; }
	.stmt-verify td { padding: 8px 10px; }
	.stmt-code { font-size: 16px; font-weight: bold; letter-spacing: 1px; margin-top: 4px; }
	.stmt-note { margin-top: 14px; color: #666; font-size: 10.5px; }

	</style>
</head>
<body>
	@include('business-statements._document')
</body>
</html>
