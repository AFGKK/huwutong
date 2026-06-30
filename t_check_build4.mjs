import { readFileSync, readdirSync } from 'fs';
const files = readdirSync('public/build/assets').filter(f => f.endsWith('.js'));
for (const f of files) {
  const c = readFileSync('public/build/assets/' + f, 'utf8');
  if (c.includes('/ai-friends/upload-avatar')) {
    console.log('Found API endpoint in:', f.substring(0, 50));
    // Find context around the match
    const idx = c.indexOf('/ai-friends/upload-avatar');
    console.log('Context:', c.substring(Math.max(0, idx - 50), idx + 80));
  }
  if (c.includes('upload-avatar')) {
    console.log('Found upload-avatar in:', f.substring(0, 50));
  }
}
console.log('Done');
