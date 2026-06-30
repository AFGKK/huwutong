import { readFileSync, readdirSync } from 'fs';
const files = ['Index-BkbZUO0b.js', 'admin-DeB1qF7b.js'];
for (const f of files) {
  const c = readFileSync('public/build/assets/' + f, 'utf8');
  const hasSubmit = c.includes('submitPlatformAi');
  const hasUpload = c.includes('uploadPlatform');
  const hasCreateAi = c.includes('createAiFriend');
  console.log(f.substring(0, 30) + ': submit=' + hasSubmit + ' upload=' + hasUpload + ' createAi=' + hasCreateAi);
}
