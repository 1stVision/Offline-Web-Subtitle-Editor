# convert xml transcript to srt format #

import xml.etree.ElementTree
import sys

def getSRTtime(timeval):

	time_sec = int(timeval.split('.')[0])
	time_mili = int(timeval.split('.')[1])
	m, s = divmod(time_sec, 60)
	h, m = divmod(m, 60)
	timev =  "%02d:%02d:%02d,%d" % (h, m, s, time_mili * 10)
	return str(timev)


def writeSRT(tr_list, outFile):
	print '[LOG|INFO] Writing SRT file at: {}'.format(outFile)
	try:
		with open(outFile, 'w') as outF:	
			for idx, val in enumerate(tr_list):
				outF.write( str(idx+1) + '\n' )
				outF.write( val['st'] + ' --> ' +  val['et'] + '\n')
				outF.write( val['spk'] + ' - ' +val['txt']+ '\n')
				outF.write('\n')
	
		outF.close()
		print "[LOG|INFO] Written SRT file at: {}".format(outFile)
	except IOError:
		print "[LOG|ERROR] Error writing SRT file"

def parseXML(xmlFile):
	
	print '[LOG|INFO] Parsing XML file : {}'.format(xmlFile)
	# extract <document> tag
	root = xml.etree.ElementTree.parse(xmlFile).getroot()
	sid=0
	tr_list = []

	# find all <sentence> tag
	for asent in root.findall('*/segment/sentence'):
		sid+=1
		tr_dict = {}
		senttext=''
		
		# get meta from <sentnece> tag
		tr_dict['st']=getSRTtime(asent.get('startTime'))
		tr_dict['et']=getSRTtime(asent.get('endTime'))
		tr_dict['id']=asent.get('id')
		tr_dict['spk']=asent.get('spkName')

		# get text from <word> tag
		for aword in asent.findall('word'):
			senttext+=aword.text+' '

		tr_dict['txt'] = senttext.strip()
		tr_list.append(tr_dict)

	print '[LOG|INFO] Parsed XML file : {}'.format(xmlFile)
	return tr_list

if __name__ == '__main__':

	xmlFile=sys.argv[1]
	srtFile=sys.argv[2]

	trList = parseXML(xmlFile)
	writeSRT(trList, srtFile)
