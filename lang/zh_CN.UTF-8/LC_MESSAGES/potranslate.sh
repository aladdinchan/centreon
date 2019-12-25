#!/bin/bash
#Simple po file translate script through google translate api v2.
#Author : CHEN JUN<aladdin.china@gmai.com> , 2019
POFILE_IN=$1
POFILE_OUT=$2
APIKEY="AIzaSyBi6jnYXRshXvPytqEPS1zXPZbXbNX2C9M" #replaced by your own google translate api key.
SOURCELANG="en"
TARGETLANG="zh-Hans"

MSGID_START=False
while read -r LINE; do
    if [[ "${LINE:0:5}" == "msgid" ]] ; then
        #msgid may have multiple line.
        MSGID_START=True
        MSGID=${LINE:7:${#LINE}-8} # substring in double quotes.
    elif [[ "${LINE:0:6}" == "msgstr" ]] ; then 
        #one msgid readed. tranlate through google api.
        if [[ "$MSGID" != "" ]] ; then
            MSGSTR=`curl -s -X POST \
                "https://translation.googleapis.com/language/translate/v2?key=$APIKEY" \
                -H 'content-type: application/json' \
                -d "{
                \"q\": \"$MSGID\",
                \"source\": \"$SOURCELANG\",
                \"target\": \"$TARGETLANG\",
                \"format\": \"text\"
                }"`
            if [[ "$?" != "0" ]] ; then
                echo "Error on call google translation api v2."
                echo "$MSGSTR"
                exit 1
            fi
            MSGSTR=`echo $MSGSTR | grep translatedText | cut -d \" -f 8`
            MSGSTR="msgstr \"$MSGSTR\""
        else 
            MSGSTR="msgstr \"\""   #blank msgid.
        fi
        echo "$MSGSTR" >> $POFILE_OUT
        
        #next msgid
        MSGID=""
        MSGID_START=False
        continue
    elif [[ "$MSGID_START" == "True" ]] ; then
        MSGID1=${LINE:1:${#LINE}-2}
        MSGID="$MSGID $MSGID1"
    fi
    echo "$LINE" >> $POFILE_OUT   #double quotes is nessasory. because msgid may having leading spaces.
done < $POFILE_IN

#End