if [ -f nem-alderstjek.zip ]; then
    rm nem-alderstjek.zip
fi

# create zip
echo 'Zipping...'
curdir=$(pwd)
ln -s "${curdir}" /tmp/nem-alderstjek
cd /tmp
zip -q -9 -r "$curdir/nem-alderstjek.zip" \
  ./nem-alderstjek/* \
  -x "nem-alderstjek/release.sh" \
  -x "nem-alderstjek/*.zip"

unlink /tmp/nem-alderstjek

echo 'Zip Done'