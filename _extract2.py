import json
path = r'C:\Users\nhatb\.cursor\projects\d-Project-Web-blog-SourceCode-CouponPage-4-12026-Blog\agent-transcripts\0b0666e2-a357-43e1-816d-5675d4cbbd37\0b0666e2-a357-43e1-816d-5675d4cbbd37.jsonl'
for i, line in enumerate(open(path, encoding='utf-8'), 1):
    if i in (3428, 3439, 4105, 4119):
        obj = json.loads(line)
        for c in obj.get('message', {}).get('content', []):
            if c.get('type') == 'tool_use' and c.get('name') == 'StrReplace':
                inp = c.get('input', {})
                p = inp.get('path', '')
                if 'home-category-sidebar' in p or 'home.blade' in p:
                    with open(r'd:\Project\Web blog\SourceCode_CouponPage_4_12026\Blog\_line_%s.txt' % i, 'w', encoding='utf-8') as o:
                        o.write(f"PATH: {p}\n\n")
                        o.write(inp.get('new_string', ''))
print('done')
