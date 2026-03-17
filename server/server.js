const express = require('express');
const cors = require('cors');
const axios = require('axios');
const cheerio = require('cheerio');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.static(__dirname));

function extractASIN(input) {
  if (!input) return null;
  const text = String(input).trim();
  if (/^[A-Z0-9]{10}$/i.test(text)) return text.toUpperCase();
  const match = text.match(/\/(?:dp|gp\/product|d|product)\/([A-Z0-9]{10})/i);
  return match ? match[1].toUpperCase() : null;
}

function normalizeMarketplace(value) {
  if (!value) return 'amazon.it';
  const cleaned = String(value).trim().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, '');
  return cleaned.startsWith('amazon.') ? cleaned : 'amazon.it';
}

function buildAmazonUrl(asin, marketplace) {
  return `https://www.${marketplace}/dp/${asin}`;
}

function cleanText(value) {
  return value ? String(value).replace(/\s+/g, ' ').trim() : null;
}

function parseAmazonHtml(html, asin, url) {
  const $ = cheerio.load(html);

  const title = cleanText($('#productTitle').text()) || cleanText($('#title').text());

  const priceSelectors = [
    '.a-price .a-offscreen',
    '.priceToPay .a-offscreen',
    '.apexPriceToPay .a-offscreen',
    '#priceblock_ourprice',
    '#priceblock_dealprice',
    '#price_inside_buybox',
    '#priceblock_saleprice'
  ];

  let price = null;
  for (const selector of priceSelectors) {
    const text = cleanText($(selector).first().text());
    if (text) {
      price = text;
      break;
    }
  }

  let image = $('#landingImage').attr('data-old-hires') || $('#landingImage').attr('src') || null;
  if (!image) {
    const dynamicImage = $('#landingImage').attr('data-a-dynamic-image');
    if (dynamicImage) {
      try {
        const json = JSON.parse(dynamicImage);
        image = Object.keys(json)[0] || null;
      } catch (_) {}
    }
  }
  if (!image) {
    image = $('#imgBlkFront').attr('src') || $('#main-image').attr('src') || null;
  }

  const availability =
    cleanText($('#availability span').text()) ||
    cleanText($('#mir-layout-DELIVERY_BLOCK-slot-PRIMARY_DELIVERY_MESSAGE_LARGE').text()) ||
    null;

  const rating =
    $('#acrPopover').attr('title') ||
    cleanText($('[data-hook="rating-out-of-text"]').first().text()) ||
    null;

  const reviews =
    cleanText($('#acrCustomerReviewText').text()) ||
    cleanText($('[data-hook="total-review-count"]').first().text()) ||
    null;

  const captcha = html.toLowerCase().includes('captcha') || html.toLowerCase().includes('enter the characters you see below');

  return {
    asin,
    url,
    title,
    price,
    image,
    rating,
    reviews,
    availability,
    captcha
  };
}

async function fetchAmazonPage(url) {
  const response = await axios.get(url, {
    timeout: 20000,
    maxRedirects: 5,
    headers: {
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
      'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
      'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
      'Cache-Control': 'no-cache',
      'Pragma': 'no-cache',
      'Upgrade-Insecure-Requests': '1'
    }
  });

  return response.data;
}

app.post('/api/price', async (req, res) => {
  try {
    const inputUrl = req.body?.url || '';
    const asin = extractASIN(req.body?.asin || inputUrl);
    const marketplace = normalizeMarketplace(req.body?.marketplace || inputUrl);

    if (!asin) {
      return res.status(400).json({ ok: false, error: 'ASIN non valido o mancante.' });
    }

    const amazonUrl = buildAmazonUrl(asin, marketplace);
    const html = await fetchAmazonPage(amazonUrl);
    const data = parseAmazonHtml(html, asin, amazonUrl);

    if (data.captcha) {
      return res.status(503).json({
        ok: false,
        error: 'Amazon ha risposto con un CAPTCHA. Riprova più tardi o usa una soluzione ufficiale/API.'
      });
    }

    if (!data.title && !data.price) {
      return res.status(404).json({
        ok: false,
        error: 'Impossibile leggere titolo o prezzo dalla pagina.'
      });
    }

    if (!data.price) {
      data.warning = 'Prodotto trovato, ma prezzo non rilevato. Amazon può aver cambiato il markup della pagina.';
    }

    return res.json({ ok: true, data });
  } catch (error) {
    const status = error.response?.status;
    const body = typeof error.response?.data === 'string' ? error.response.data : '';

    if (status === 503 || body.toLowerCase().includes('captcha')) {
      return res.status(503).json({
        ok: false,
        error: 'Amazon ha bloccato temporaneamente la richiesta con CAPTCHA o 503.'
      });
    }

    return res.status(500).json({
      ok: false,
      error: `Errore server: ${error.message}`
    });
  }
});

app.get('/api/health', (_req, res) => {
  res.json({ ok: true, service: 'amazon-price-checker' });
});

app.listen(PORT, () => {
  console.log(`Server attivo su http://localhost:${PORT}`);
});
