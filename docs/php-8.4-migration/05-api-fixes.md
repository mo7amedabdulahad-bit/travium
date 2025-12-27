# API Fixes

## API Architecture

The Travium API uses **subdomain routing**:
- ✅ Correct: `https://api.travium.local/v1/register/register`
- ❌ Wrong: `https://travium.local/api/v1/register`

Nginx configuration uses `map $host $root_path` to route subdomains.

---

## Fix #17: Translator Locale Error Message
**File**: `integrations/api/include/Core/Translator.php`  
**Line**: 49

### Issue
Error message tried to print `$locale` variable **after** it was exploded into an array:
```php
$locale = explode("-", $locale);  // Now an array!
if(sizeof($locale) <> 2){
    throw new ErrorException("Invalid locale \"$locale\"");  // Prints "Array"
}
```

### Solution
Store original string before exploding:
```php
$originalLocale = $locale;  // Store for error messages
$locale = explode("-", $locale);
if(sizeof($locale) !== 2){
    throw new ErrorException("Invalid locale \"$originalLocale\"");  // Prints actual locale
}
```

**Impact**: Meaningful error messages instead of "Invalid locale 'Array'".

---

## API Registration Flow

### Request Format
```json
POST https://api.travium.local/v1/register/register
Content-Type: application/json

{
  "gameWorld": 1,
  "username": "testuser",
  "email": "test@test.com",
  "termsAndConditions": true,
  "lang": "en-US"
}
```

### Response Format
```json
{
  "success": true,
  "error": {
    "errorType": null,
    "errorMsg": null
  },
  "data": {
    "success": false  // Business logic validation
  }
}
```

### Locale Format
Must be in `language-COUNTRY` format:
- ✅ `en-US`, `fa-IR`
- ❌ `en`, `fa`

---

## API Testing

Tested endpoints:
1. ✅ `/v1/config` - Server configuration
2. ✅ `/v1/register/register` - User registration
3. ✅ API responds without PHP errors
4. ✅ Proper error messages
5. ✅ JSON responses well-formed

**Result**: API fully functional on PHP 8.4!

---

## API Subdomain Routing

From `/etc/nginx/sites-enabled/travium.local.conf`:

```nginx
map $host $root_path {
    default                                               "/home/travium/htdocs/homepage";
    ~^(voting|payment|api|cdn|install)\.travium\.local$  "/home/travium/htdocs/integrations/$1";
    ~^(?<sub>[a-z0-9-]+)\.travium\.local$                "/home/travium/htdocs/servers/${sub}/public";
}
```

### Subdomain Breakdown
| Subdomain | Path | Purpose |
|-----------|------|---------|
| `api.travium.local` | `/integrations/api` | API endpoints |
| `voting.travium.local` | `/integrations/voting` | Voting system |
| `payment.travium.local` | `/integrations/payment` | Payments |
| `cdn.travium.local` | `/integrations/cdn` | Static assets |
| `s1.travium.local` | `/servers/s1/public` | Game server 1 |
| `s2.travium.local` | `/servers/s2/public` | Game server 2 |
| `travium.local` | `/homepage` | Main website |

**Important**: Frontend must call `api.travium.local`, not `/api` path!
