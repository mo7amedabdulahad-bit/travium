"""
Extract all mobile CSS rules from Travian's compressed CSS file
"""

import re

# Read the compressed CSS file
with open('imports_compressed.css', 'r', encoding='utf-8') as f:
    css_content = f.read()

# Find all mobile-related CSS blocks
mobile_patterns = [
    r'\.mobileOptimized[^{]*\{[^}]+\}',
    r'\.mobileForced[^{]*\{[^}]+\}',
    r'@media screen and \(max-width:620px\)[^{]*\{[^}]+\}',
    r'@media screen and \(max-width:620px\) and \(orientation:portrait\)[^{]*\{(?:[^{}]|\{[^}]*\})*\}',
]

mobile_css = []
mobile_css.append("/* ========================================")
mobile_css.append("   TRAVIAN OFFICIAL MOBILE CSS")
mobile_css.append("   Extracted from imports_compressed.css")
mobile_css.append("   ======================================== */\n")

# Extract .mobileOptimized rules
print("Extracting .mobileOptimized rules...")
mobile_optimized = re.findall(r'\.mobileOptimized[^{]*?\{[^}]+?\}', css_content)
print(f"Found {len(mobile_optimized)} .mobileOptimized rules")

if mobile_optimized:
    mobile_css.append("\n/* ========== .mobileOptimized Rules ========== */\n")
    for rule in mobile_optimized[:50]:  # Limit to first 50
        mobile_css.append(rule)
        mobile_css.append("\n")

# Extract .mobileForced rules
print("Extracting .mobileForced rules...")
mobile_forced = re.findall(r'\.mobileForced[^{]*?\{[^}]+?\}', css_content)
print(f"Found {len(mobile_forced)} .mobileForced rules")

if mobile_forced:
    mobile_css.append("\n/* ========== .mobileForced Rules ========== */\n")
    for rule in mobile_forced[:50]:  # Limit to first 50
        mobile_css.append(rule)
        mobile_css.append("\n")

# Extract @media queries (this is complex due to nested braces)
print("Extracting @media rules...")
media_start = 0
while True:
    media_start = css_content.find('@media screen and (max-width:620px)', media_start)
    if media_start == -1:
        break
    
    # Find the matching closing brace
    brace_count = 0
    pos = media_start
    start_found = False
    
    while pos < len(css_content):
        if css_content[pos] == '{':
            brace_count += 1
            start_found = True
        elif css_content[pos] == '}':
            brace_count -= 1
            if start_found and brace_count == 0:
                # Found the end
                media_block = css_content[media_start:pos+1]
                mobile_css.append("\n/* ========== Media Query Block ========== */\n")
                mobile_css.append(media_block)
                mobile_css.append("\n")
                break
        pos += 1
    
    media_start = pos + 1

# Write extracted CSS
output_file = 'travian_mobile_extracted.css'
with open(output_file, 'w', encoding='utf-8') as f:
    f.write('\n'.join(mobile_css))

print(f"\nExtraction complete!")
print(f"Output written to: {output_file}")
print(f"Total rules extracted: {len(mobile_optimized) + len(mobile_forced)}")
