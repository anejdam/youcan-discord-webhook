import express from "express";
import crypto from "crypto";
import fetch from "node-fetch";

const app = express();
app.use(express.json());

const DISCORD_URL = process.env.DISCORD_WEBHOOK_URL;
const SIGNING_KEY = process.env.YOUCAN_CLIENT_SECRET;

app.post("/", async (req, res) => {
  const payload = req.body;
  const signature = req.headers["x-youcan-signature"] || "";

  const expected = crypto
    .createHmac("sha256", SIGNING_KEY)
    .update(JSON.stringify(payload))
    .digest("hex");

  if (expected !== signature) {
    return res.status(401).send("Invalid signature");
  }

  const order = payload;
  const shipping = order.shipping_address || {};

  const first = shipping.first_name || "";
  const last = shipping.last_name || "";
  const phone = shipping.phone || "";
  const city = shipping.city || "";

  const variant = order.variants?.[0] || {};
  const product = variant.variant?.product?.name || "";
  const values = variant.variant?.values || "";

  let raw = Array.isArray(values) ? values.join(",") : String(values);
  raw = raw.split(" /")[0];
  const [color = "", size = ""] = raw.split(",");

  const price = variant.price || "";
  const qty = variant.quantity || 1;

  const ref = order.ref || "";
  const total = order.total || "";
  const created = order.created_at || "";

  const linkShow = order.links?.self || "";
  const linkEdit = order.links?.edit || "";

  const desc = `
____________________________________
📋 **رقم الطلبية**
\`${ref}\`

____________________________________
👤 **معلومات العميل**
الاسم : ${first} ${last}
الهاتف : ${phone}
المدينة : ${city}

____________________________________
🎯 **المنتج**
${product}
اللون : ${color}
المقاس : ${size}
السعر : ${price} درهم
الكمية : x${qty}

____________________________________
💰 **المجموع الإجمالي**
\`${total} درهم\`

____________________________________
🔗 [عرض الطلبية](${linkShow}) | [تعديل الطلبية](${linkEdit})
`;

  const body = {
    content: "",
    embeds: [
      {
        title: "📦 طلبية جديدة وصلت!",
        description: desc,
        color: 0x2ecc71
      }
    ]
  };

  await fetch(DISCORD_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body)
  });

  res.send("OK");
});

const port = process.env.PORT || 10000;
app.listen(port, () => console.log("Running on port " + port));
