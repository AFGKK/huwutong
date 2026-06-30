import { readFileSync, readdirSync } from 'fs';
const files = readdirSync('public/build/assets').filter(f => f.endsWith('.js'));
for (const f of files) {
  const c = readFileSync('public/build/assets/' + f, 'utf8');
  if (c.includes('upload-avatar')) {
    // Check if the el-upload code is present
    if (c.includes('el-upload') || c.includes('http-request')) {
      console.log('Upload code found in:', f.substring(0, 50));
      const idx = c.indexOf('upload-avatar');
      console.log('Context:', c.substring(Math.max(0, idx - 30), idx + 60));
    }
  }
}
console.log('Done');
