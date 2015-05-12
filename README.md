# weixin_api

微信API，用于统一管理 `access_token` ，`jssdk` 签名。

项目基于 `yaf` 框架。

api.db 数据表：

```sql
CREATE TABLE "weixin" (
"id"  INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
"appId"  TEXT NOT NULL,
"appSecret"  TEXT NOT NULL,
"access_token"  TEXT,
"access_token_expires"  INTEGER,
"jsapi_ticket"  TEXT,
"jsapi_ticket_expires"  INTEGER
);
CREATE TABLE "weixin_jsapi_security_domain" (
"id"  INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
"weixin_id"  INTEGER NOT NULL,
"jsapi_security_domain"  TEXT NOT NULL
);
CREATE TABLE "weixin_user" (
"openid"  TEXT NOT NULL,
"nickname"  TEXT,
"sex"  TEXT,
"province"  TEXT,
"city"  TEXT,
"country"  TEXT,
"headimgurl"  TEXT,
"privilege"  TEXT,
"unionid"  TEXT,
PRIMARY KEY ("openid")
);
CREATE UNIQUE INDEX "appid"
ON "weixin" ("appid" ASC);
CREATE UNIQUE INDEX "appsecret"
ON "weixin" ("appsecret" ASC);
CREATE INDEX "jsapi_security_domain"
ON "weixin_jsapi_security_domain" ("jsapi_security_domain" ASC);
CREATE INDEX "weixin_id"
ON "weixin_jsapi_security_domain" ("weixin_id" ASC);
```

Nginx URL Rewrite:
```json
location {
    ...
    try_files $uri $uri/ /index.php$query_string;
}
```