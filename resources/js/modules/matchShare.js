// Match result share image. Renders a branded 16:9 PNG card (rounded corners,
// transparent outside the corners) from a confirmed match's data attributes and
// downloads it — no server round-trip, no libraries.

export function initMatchShare() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-share-match]');
    if (!btn) return;
    e.preventDefault();
    const data = JSON.parse(btn.dataset.shareMatch);
    renderAndDownload(data);
  });
}

function renderAndDownload(d) {
  // d = { tournament, category, context, pairA, pairB, sets:[[a,b],...], winner:'a'|'b' }
  const scale = 2;                 // retina
  const W = 1200, H = 675;         // 16:9
  const R = 48;                    // corner radius
  const canvas = document.createElement('canvas');
  canvas.width = W * scale;
  canvas.height = H * scale;
  const ctx = canvas.getContext('2d');
  ctx.scale(scale, scale);

  const indigo = '#635bff';
  const dark = '#13131f';
  const gold = '#d4af37';

  // Transparent everywhere by default; clip to a rounded rect so the corners
  // outside the radius stay transparent.
  ctx.clearRect(0, 0, W, H);
  roundRectPath(ctx, 0, 0, W, H, R);
  ctx.clip();

  // Background gradient (only fills inside the clip).
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0, '#1a1a2e');
  g.addColorStop(1, dark);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, W, H);

  // Top accent bar.
  ctx.fillStyle = indigo;
  ctx.fillRect(0, 0, W, 10);

  const padX = 70;

  // Header: tournament (muted) + category (indigo) + context (faint).
  ctx.textAlign = 'left';
  ctx.fillStyle = 'rgba(255,255,255,0.6)';
  ctx.font = '600 24px Inter, Arial, sans-serif';
  ctx.fillText(truncate(d.tournament, 46).toUpperCase(), padX, 78);

  ctx.fillStyle = indigo;
  ctx.font = '700 40px Inter, Arial, sans-serif';
  ctx.fillText(truncate(d.category || '', 32), padX, 124);

  if (d.context) {
    ctx.fillStyle = 'rgba(255,255,255,0.45)';
    ctx.font = '500 22px Inter, Arial, sans-serif';
    ctx.fillText(d.context.toUpperCase(), padX, 158);
  }

  // "RESULTADO FINAL" tag, right-aligned.
  ctx.textAlign = 'right';
  ctx.fillStyle = gold;
  ctx.font = '700 22px Inter, Arial, sans-serif';
  ctx.fillText('RESULTADO FINAL', W - padX, 100);

  // Two pair rows in the middle band.
  const winA = d.winner === 'a';
  const winB = d.winner === 'b';
  drawPairRow(ctx, W, padX, 290, d.pairA, d.sets.map((s) => s[0]), winA, { indigo, gold });

  // Divider + VS.
  ctx.strokeStyle = 'rgba(255,255,255,0.1)';
  ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(padX, 345); ctx.lineTo(W - padX, 345); ctx.stroke();
  ctx.textAlign = 'left';
  ctx.fillStyle = 'rgba(255,255,255,0.3)';
  ctx.font = '700 22px Inter, Arial, sans-serif';
  ctx.fillText('VS', padX, 352);

  drawPairRow(ctx, W, padX, 430, d.pairB, d.sets.map((s) => s[1]), winB, { indigo, gold });

  // Winner ribbon.
  const wName = winA ? d.pairA : winB ? d.pairB : null;
  if (wName) {
    ctx.textAlign = 'left';
    ctx.fillStyle = gold;
    ctx.font = '700 26px Inter, Arial, sans-serif';
    ctx.fillText('\u{1F3C6} ' + truncate(wName, 40), padX, 560);
  }

  // Brand bottom-right.
  ctx.textAlign = 'right';
  ctx.fillStyle = 'rgba(255,255,255,0.4)';
  ctx.font = '600 24px Inter, Arial, sans-serif';
  ctx.fillText('PadelCup', W - padX, 615);

  canvas.toBlob((blob) => {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = slug(d.category || 'partido') + '-resultado.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }, 'image/png');
}

function drawPairRow(ctx, W, padX, y, name, scores, isWinner, c) {
  ctx.textAlign = 'left';
  let nameX = padX;
  if (isWinner) {
    ctx.fillStyle = c.gold;
    ctx.beginPath(); ctx.arc(padX + 8, y - 14, 8, 0, Math.PI * 2); ctx.fill();
    nameX = padX + 30;
  }
  ctx.fillStyle = isWinner ? '#fff' : 'rgba(255,255,255,0.7)';
  ctx.font = (isWinner ? '700 ' : '500 ') + '40px Inter, Arial, sans-serif';
  ctx.fillText(truncate(name, 26), nameX, y);

  ctx.textAlign = 'center';
  const box = 56, gap = 14;
  const totalW = scores.length * box + (scores.length - 1) * gap;
  let x = W - padX - totalW + box / 2;
  scores.forEach((s) => {
    ctx.fillStyle = isWinner ? c.indigo : 'rgba(255,255,255,0.12)';
    roundRectPath(ctx, x - box / 2, y - 44, box, 56, 10);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.font = '700 34px Inter, Arial, sans-serif';
    ctx.fillText(String(s), x, y - 4);
    x += box + gap;
  });
}

function roundRectPath(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function truncate(s, n) {
  s = s || '';
  return s.length > n ? s.slice(0, n - 1) + '\u2026' : s;
}

function slug(s) {
  return (s || '').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}
