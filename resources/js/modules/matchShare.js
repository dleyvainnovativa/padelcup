// Strava-style match result image. Renders a branded PNG card from a confirmed
// match's data attributes and downloads it — no server round-trip, no libraries.

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
  const scale = 2; // retina
  const W = 1080, H = 1080;
  const canvas = document.createElement('canvas');
  canvas.width = W * scale;
  canvas.height = H * scale;
  const ctx = canvas.getContext('2d');
  ctx.scale(scale, scale);

  // Theme colors.
  const indigo = '#635bff';
  const dark = '#13131f';
  const gold = '#d4af37';

  // Background gradient.
  const g = ctx.createLinearGradient(0, 0, W, H);
  g.addColorStop(0, '#1a1a2e');
  g.addColorStop(1, dark);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, W, H);

  // Accent corner band.
  ctx.fillStyle = indigo;
  ctx.fillRect(0, 0, W, 12);

  // Header: tournament + category.
  ctx.textAlign = 'center';
  ctx.fillStyle = 'rgba(255,255,255,0.65)';
  ctx.font = '600 30px Inter, Arial, sans-serif';
  ctx.fillText(truncate(d.tournament, 36).toUpperCase(), W / 2, 110);

  ctx.fillStyle = indigo;
  ctx.font = '700 40px Inter, Arial, sans-serif';
  ctx.fillText(d.category || '', W / 2, 165);

  ctx.fillStyle = 'rgba(255,255,255,0.45)';
  ctx.font = '500 26px Inter, Arial, sans-serif';
  ctx.fillText((d.context || '').toUpperCase(), W / 2, 210);

  // "RESULTADO FINAL" tag.
  ctx.fillStyle = gold;
  ctx.font = '700 24px Inter, Arial, sans-serif';
  ctx.fillText('RESULTADO FINAL', W / 2, 300);

  // Pairs + scores.
  const winA = d.winner === 'a';
  const winB = d.winner === 'b';
  drawPairRow(ctx, W, 400, d.pairA, d.sets.map((s) => s[0]), winA, { indigo, gold });
  // VS divider
  ctx.strokeStyle = 'rgba(255,255,255,0.1)';
  ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(120, 560); ctx.lineTo(W - 120, 560); ctx.stroke();
  ctx.fillStyle = 'rgba(255,255,255,0.3)';
  ctx.font = '700 28px Inter, Arial, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('VS', W / 2, 570);
  drawPairRow(ctx, W, 660, d.pairB, d.sets.map((s) => s[1]), winB, { indigo, gold });

  // Winner ribbon.
  const wName = winA ? d.pairA : winB ? d.pairB : null;
  if (wName) {
    ctx.fillStyle = gold;
    ctx.font = '700 30px Inter, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('\u{1F3C6} ' + truncate(wName, 34), W / 2, 880);
  }

  // Footer brand.
  ctx.fillStyle = 'rgba(255,255,255,0.4)';
  ctx.font = '600 26px Inter, Arial, sans-serif';
  ctx.fillText('PadelCup', W / 2, 1010);

  // Download.
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

function drawPairRow(ctx, W, y, name, scores, isWinner, c) {
  // Name.
  ctx.textAlign = 'left';
  ctx.fillStyle = isWinner ? '#fff' : 'rgba(255,255,255,0.7)';
  ctx.font = (isWinner ? '700 ' : '500 ') + '44px Inter, Arial, sans-serif';
  ctx.fillText(truncate(name, 22), 120, y);

  if (isWinner) {
    // small gold dot
    ctx.fillStyle = c.gold;
    ctx.beginPath(); ctx.arc(96, y - 14, 8, 0, Math.PI * 2); ctx.fill();
  }

  // Scores on the right.
  ctx.textAlign = 'center';
  const startX = W - 120 - (scores.length - 1) * 70;
  scores.forEach((s, i) => {
    const x = startX + i * 70;
    ctx.fillStyle = isWinner ? c.indigo : 'rgba(255,255,255,0.12)';
    roundRect(ctx, x - 28, y - 48, 56, 60, 10);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.font = '700 38px Inter, Arial, sans-serif';
    ctx.fillText(String(s), x, y - 6);
  });
}

function roundRect(ctx, x, y, w, h, r) {
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
