
import re

filename = 'main.js'

print(f"--- Extracting Mobile Logic from {filename} ---\n")

try:
    with open(filename, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()

    # Find definition of MobileOptimizations
    # Looking for something like "MobileOptimizations"
    mobile_opt_matches = [m.start() for m in re.finditer(r'MobileOptimizations', content)]
    
    for i, pos in enumerate(mobile_opt_matches):
        start = max(0, pos - 500)
        end = min(len(content), pos + 1000)
        context = content[start:end]
        print(f"--- Match {i+1} (MobileOptimizations) ---")
        print(context)
        print("\n" + "="*50 + "\n")

    # Find usage of mobileOptimized class
    class_matches = [m.start() for m in re.finditer(r'mobileOptimized', content)]
    
    for i, pos in enumerate(class_matches):
        start = max(0, pos - 300)
        end = min(len(content), pos + 500)
        context = content[start:end]
        print(f"--- Match {i+1} (mobileOptimized) ---")
        print(context)
        print("\n" + "="*50 + "\n")

except Exception as e:
    print(f"Error: {e}")
