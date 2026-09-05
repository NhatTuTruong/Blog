import json
import sys

sys.stdout.reconfigure(encoding='utf-8')
path = r'C:\Users\nhatb\.cursor\projects\d-Project-Web-blog-SourceCode-CouponPage-4-12026-Blog\agent-transcripts\0b0666e2-a357-43e1-816d-5675d4cbbd37\0b0666e2-a357-43e1-816d-5675d4cbbd37.jsonl'
out = r'd:\Project\Web blog\SourceCode_CouponPage_4_12026\Blog\_transcript_extract.txt'
lines_to_get = [3415, 3423, 3429, 3439, 4109]
with open(path, encoding='utf-8') as f, open(out, 'w', encoding='utf-8') as o:
    for i, line in enumerate(f, 1):
        if i in lines_to_get:
            obj = json.loads(line)
            o.write(f'\n\n===== LINE {i} =====\n')
            for c in obj.get('message', {}).get('content', []):
                if c.get('type') == 'tool_use':
                    inp = c.get('input', {})
                    o.write(f"TOOL: {c['name']} {inp.get('path','')}\n")
                    text = inp.get('contents') or inp.get('new_string') or inp.get('old_string') or ''
                    o.write(text)
                    o.write('\n')
                elif c.get('type') == 'text':
                    o.write('TEXT: ' + c.get('text', '') + '\n')
print('written')
