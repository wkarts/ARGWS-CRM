#!/usr/bin/env python3
"""Compile application-owned PHP before starting databases; never execute it."""
import concurrent.futures,pathlib,subprocess,sys
ROOT=pathlib.Path(__file__).resolve().parents[2]
def owned(path):return path.endswith('.php') and not {'vendor','third_party','node_modules'}.intersection(pathlib.PurePosixPath(path).parts)
def tracked_sources(root=ROOT,execute=subprocess.check_output):
    # Checkout ownership differs from the container user. Trust this exact
    # workspace only for this read, never a wildcard or persistent global setting.
    return [p for p in execute(['git','-c','safe.directory='+str(root),'ls-files','-z','--','application','modules','scripts','tests'],cwd=root).decode().split('\0') if p and owned(p)]
def main():
    paths=tracked_sources()
    def check(path):
        r=subprocess.run(['php','-l',path],cwd=ROOT,capture_output=True,text=True,timeout=30)
        return path,r.returncode,r.stdout+r.stderr
    errors=[]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        for path,code,output in pool.map(check,paths):
            if code:errors.append((path,output));print(output,file=sys.stderr)
    print(f'PHP syntax: {len(paths)} application-owned tracked files; {len(errors)} errors. Dependencies remain in their dedicated checks.')
    return bool(errors)
if __name__=='__main__':sys.exit(main())
