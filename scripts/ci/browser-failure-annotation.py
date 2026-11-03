#!/usr/bin/env python3
"""Keep the final stack trace of an already sanitized failure below the public UI cap."""
import pathlib,sys

def annotation(message):
    header,_,body=message.partition('\n')
    header=header.encode('utf-8')[:512].decode('utf-8',errors='replace')
    tail=body.encode('utf-8')[-2800:].decode('utf-8',errors='replace')
    compact=header+'\n'+tail
    escaped=compact.replace('%','%25').replace('\r','%0D').replace('\n','%0A')
    return '::error title=Isolated browser failure::'+escaped

if __name__=='__main__':print(annotation(pathlib.Path(sys.argv[1]).read_text()))
