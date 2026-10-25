"""Build and validate a self-contained local Symcon installation ZIP."""
import argparse
import hashlib
import json
import uuid
import zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
lib=json.loads((root/'library.json').read_text())
mod=json.loads((root/'Vitocal222CTile/module.json').read_text())
for manifest in (lib,mod): uuid.UUID(manifest['id'].strip('{}'))
assert lib['id'] != mod['id']
assert lib['id'] != '{B24E29F4-981D-4B92-842B-785D28962BD5}'
assert mod['id'] != '{4305595E-0E5F-4124-AD59-8E11257A1E97}'
assert mod['prefix']=='V222' and lib['compatibility']['version']=='7.1'
files=['library.json','Vitocal222CTile/module.json','Vitocal222CTile/module.php','Vitocal222CTile/tile.html','README.md','CHANGELOG.md','docs/VALIDATION.md','docs/dashboard.png','docs/history.png']
assert all((root/p).is_file() for p in files)
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output-dir',type=Path,default=root.parent/(root.name+'-artifacts'))
args=parser.parse_args()
out=args.output_dir.resolve()
if out == root or root in out.parents:
    parser.error('Build output must be outside the Symcon library repository.')
# Same reserved directories as official SymconStubs ModuleLoader::loadModules.
reserved={'libs','docs','imgs','tests','actions'}
for directory in root.iterdir():
    if directory.is_dir() and not directory.name.startswith('.') and directory.name not in reserved:
        assert (directory/'module.json').is_file(), f'Invalid Symcon module directory: {directory.name}'
out.mkdir(parents=True,exist_ok=True)
archive=out/f"Vitocal222C-Kachel-{lib['version']}.zip"
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    for p in sorted(files): z.write(root/p,'Vitocal222C_Kachel/'+p)
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    assert set(z.namelist()) == {'Vitocal222C_Kachel/'+p for p in files}
    assert json.loads(z.read('Vitocal222C_Kachel/library.json')) == lib
archive.with_suffix('.zip.sha256').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(f'PASS: manifests, distinct identities, ZIP integrity and {len(files)} packaged files: {archive}')
